<?php
class estados
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /** Procesa vencimientos y publicaciones programadas desde cualquier petición API. */
    public function procesarTareasProgramadas()
    {
        try {
            $hayVencidos = (bool)$this->pdo->query(
                "SELECT 1 FROM estados WHERE fecha_expiracion <= NOW() LIMIT 1"
            )->fetchColumn();
            $hayProgramados = (bool)$this->pdo->query(
                "SELECT 1 FROM estados_programados WHERE estado = 'pendiente' AND programar_en <= NOW() LIMIT 1"
            )->fetchColumn();

            if ($hayVencidos) {
                $this->limpiarEstadosVencidos();
            }
            if (!$hayProgramados) {
                return;
            }

            $this->pdo->beginTransaction();
            $stmt = $this->pdo->query(
                "SELECT * FROM estados_programados
                 WHERE estado = 'pendiente' AND programar_en <= NOW()
                 ORDER BY programar_en, id LIMIT 50 FOR UPDATE"
            );
            $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pendientes as $programado) {
                $horas = max(24, min(168, (int)$programado['duracion_horas']));
                $sql = "INSERT INTO estados
                    (usuario_id, tipo, contenido, archivo_id, fecha_creacion, fecha_expiracion, estado)
                    VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL {$horas} HOUR), 'activo')";
                $insertar = $this->pdo->prepare($sql);
                $insertar->execute([
                    $programado['usuario_id'],
                    $programado['tipo'],
                    $programado['contenido'],
                    $programado['archivo_id'],
                ]);
                $estadoId = (int)$this->pdo->lastInsertId();
                $this->guardarAudienciaYNotificar($estadoId, (int)$programado['usuario_id']);

                $siguiente = null;
                if ((int)$programado['repetir_semanal'] === 1 && !empty($programado['repetir_hasta'])) {
                    $zona = new DateTimeZone('America/Mexico_City');
                    $ahora = new DateTimeImmutable($this->pdo->query("SELECT NOW()")->fetchColumn(), $zona);
                    $siguiente = (new DateTimeImmutable($programado['programar_en'], $zona))->modify('+7 days');
                    while ($siguiente <= $ahora) {
                        $siguiente = $siguiente->modify('+7 days');
                    }
                    if ($siguiente->format('Y-m-d') > $programado['repetir_hasta']) {
                        $siguiente = null;
                    }
                }

                if ($siguiente) {
                    $actualizar = $this->pdo->prepare(
                        "UPDATE estados_programados SET programar_en = ? WHERE id = ? AND estado = 'pendiente'"
                    );
                    $actualizar->execute([$siguiente->format('Y-m-d H:i:s'), $programado['id']]);
                } else {
                    $actualizar = $this->pdo->prepare(
                        "UPDATE estados_programados SET estado = 'publicada', fecha_publicacion = NOW() WHERE id = ?"
                    );
                    $actualizar->execute([$programado['id']]);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('procesarTareasProgramadas: ' . $e->getMessage());
        }
    }

    /**
     * Limpieza oportunista: se ejecuta cada vez que el controlador instancia esta clase.
     * El archivo solo se elimina si ningún estado ni mensaje lo sigue utilizando.
     */
    private function limpiarEstadosVencidos()
    {
        $archivosFisicosPendientes = [];

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->query(
                "SELECT DISTINCT a.id, a.nombre_archivo
                 FROM archivos a
                 INNER JOIN estados e ON e.archivo_id = a.id
                 WHERE e.fecha_expiracion <= NOW()"
            );
            $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // estados_vistos se elimina automáticamente por la FK al borrar estados.
            $this->pdo->exec("DELETE FROM estados WHERE fecha_expiracion <= NOW()");

            foreach ($candidatos as $archivo) {
                $archivoId = (int)$archivo['id'];
                $stmt = $this->pdo->prepare(
                    "SELECT
                        (SELECT COUNT(*) FROM estados WHERE archivo_id = ?) +
                        (SELECT COUNT(*) FROM mensaje_archivos WHERE archivo_id = ?) +
                        (SELECT COUNT(*) FROM estados_programados WHERE archivo_id = ? AND estado = 'pendiente')"
                );
                $stmt->execute([$archivoId, $archivoId, $archivoId]);

                if ((int)$stmt->fetchColumn() !== 0) {
                    continue;
                }

                $stmt = $this->pdo->prepare("DELETE FROM archivos WHERE id = ?");
                $stmt->execute([$archivoId]);
                if ($stmt->rowCount() > 0 && !empty($archivo['nombre_archivo'])) {
                    $archivosFisicosPendientes[] = basename($archivo['nombre_archivo']);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('limpiarEstadosVencidos: ' . $e->getMessage());
            return;
        }

        $carpetaUploads = dirname(__DIR__) . '/uploads/estados/';
        foreach (array_unique($archivosFisicosPendientes) as $nombreArchivo) {
            $rutaFisica = $carpetaUploads . $nombreArchivo;
            if (is_file($rutaFisica) && !unlink($rutaFisica)) {
                error_log('No se pudo borrar el archivo vencido: ' . $rutaFisica);
            }
        }
    }

    private function validarSesion($token)
    {
        $sql = "SELECT usuario_id FROM sesiones WHERE token = ? AND estado = 'activa' LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$token]);
        return $stmt->fetchColumn();
    }

    private function privacidadPermite($propietarioId, $visualizadorId, $campo)
    {
        if ((int)$propietarioId === (int)$visualizadorId) return true;

        $restriccion = [
            'foto_perfil' => 'ocultar_foto',
            'descripcion' => 'ocultar_descripcion',
        ];
        if (isset($restriccion[$campo])) {
            $stmt = $this->pdo->prepare(
                "SELECT {$restriccion[$campo]} FROM privacidad_restricciones_contacto WHERE usuario_id = ? AND contacto_id = ? LIMIT 1"
            );
            $stmt->execute([$propietarioId, $visualizadorId]);
            if ((int)$stmt->fetchColumn() === 1) return false;
        }

        $campos = ['foto_perfil' => 'foto_perfil', 'descripcion' => 'descripcion'];
        if (!isset($campos[$campo])) return false;
        $stmt = $this->pdo->prepare("SELECT {$campos[$campo]} FROM privacidad_usuario WHERE usuario_id = ? LIMIT 1");
        $stmt->execute([$propietarioId]);
        $visibilidad = $stmt->fetchColumn() ?: 'contactos';
        if ($visibilidad === 'nadie') return false;

        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM contactos WHERE usuario_id = ? AND contacto_id = ? LIMIT 1"
        );
        $stmt->execute([$propietarioId, $visualizadorId]);
        $esContacto = (bool)$stmt->fetchColumn();
        if ($visibilidad === 'contactos' && !$esContacto) return false;
        return true;
    }

    private function audienciaPermite($estadoId, $visualizadorId)
    {
        $stmt = $this->pdo->prepare("SELECT usuario_id FROM estados WHERE id = ? LIMIT 1");
        $stmt->execute([$estadoId]);
        $propietarioId = $stmt->fetchColumn();
        if (!$propietarioId) return false;
        if ((int)$propietarioId === (int)$visualizadorId) return true;

        $stmt = $this->pdo->prepare(
            "SELECT ocultar_estados FROM privacidad_restricciones_contacto WHERE usuario_id = ? AND contacto_id = ? LIMIT 1"
        );
        $stmt->execute([$propietarioId, $visualizadorId]);
        if ((int)$stmt->fetchColumn() === 1) return false;

        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM estado_audiencia WHERE estado_id = ? AND usuario_id = ? LIMIT 1"
        );
        $stmt->execute([$estadoId, $visualizadorId]);
        return (bool)$stmt->fetchColumn();
    }
    private function urlRuta($ruta)
    {
        if (empty($ruta)) return '';

        $base = 'https://eqtc5661rodrigo.tail4cd307.ts.net';

        if (preg_match('#^https?://#i', $ruta)) {
            return preg_replace('#^https?://(localhost|127\.0\.0\.1|192\.168\.[\d.]+)(:\d+)?#i', $base, $ruta);
        }

        $ruta = preg_replace('#^/new(%20| )app/api#i', '', $ruta);
        return $base . '/' . ltrim($ruta, '/');
    }

    private function subirArchivo($archivo, $tiposPermitidos, $carpetaRelativa, $maxSize = 20971520)
    {
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        if ($archivo['size'] <= 0) {
            return false;
        }
        if ($archivo['size'] > $maxSize) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $tipoMime = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);

        if (!isset($tiposPermitidos[$tipoMime])) {
            return false;
        }

        $extension = $tiposPermitidos[$tipoMime];
        $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
        $carpetaFisica = dirname(__DIR__) . '/uploads/estados/';

        if (!is_dir($carpetaFisica)) {
            if (!mkdir($carpetaFisica, 0777, true)) {
                return false;
            }
        }

        $rutaFisica = $carpetaFisica . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) {
            return false;
        }

        $ruta = '/new%20app/api/uploads/estados/' . $nombreArchivo;

        $sql = "
            INSERT INTO archivos
            (
                nombre_original,
                nombre_archivo,
                ruta,
                tipo_mime,
                extension,
                tamano,
                hash_archivo,
                fecha_creacion
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                NOW()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $archivo['name'],
            $nombreArchivo,
            $ruta,
            $tipoMime,
            $extension,
            $archivo['size'],
            hash_file('sha256', $rutaFisica)
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function tiposImagen()
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
    }

    private function tiposVideo()
    {
        return [
            'video/mp4'      => 'mp4',
            'video/webm'     => 'webm',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
        ];
    }

    private function destinatariosSegunPrivacidad($usuarioId)
    {
        $stmt = $this->pdo->prepare("SELECT estados FROM privacidad_usuario WHERE usuario_id = ? LIMIT 1");
        $stmt->execute([$usuarioId]);
        $modo = $stmt->fetchColumn() ?: 'contactos';

        if ($modo === 'nadie') return [];

        if ($modo === 'todos') {
            $stmt = $this->pdo->prepare(
                "SELECT u.id FROM usuarios u
                 WHERE u.id <> ? AND u.estado = 'activo'
                   AND NOT EXISTS (SELECT 1 FROM bloqueos b WHERE b.usuario_id = ? AND b.usuario_bloqueado_id = u.id)
                   AND NOT EXISTS (SELECT 1 FROM bloqueos b WHERE b.usuario_id = u.id AND b.usuario_bloqueado_id = ?)"
            );
            $stmt->execute([$usuarioId, $usuarioId, $usuarioId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        if ($modo === 'solo_seleccionados') {
            $condicionSeleccion = "EXISTS (
                SELECT 1 FROM privacidad_estados_contactos pe
                WHERE pe.usuario_id = c.usuario_id AND pe.contacto_id = c.contacto_id AND pe.accion = 'compartir'
            )";
        } elseif ($modo === 'contactos_excepto') {
            $condicionSeleccion = "NOT EXISTS (
                SELECT 1 FROM privacidad_estados_contactos pe
                WHERE pe.usuario_id = c.usuario_id AND pe.contacto_id = c.contacto_id AND pe.accion = 'excluir'
            )";
        } else {
            $condicionSeleccion = '1 = 1';
        }

        $stmt = $this->pdo->prepare(
            "SELECT c.contacto_id FROM contactos c
             WHERE c.usuario_id = ? AND c.contacto_id <> ?
               AND {$condicionSeleccion}
               AND NOT EXISTS (SELECT 1 FROM bloqueos b WHERE b.usuario_id = c.usuario_id AND b.usuario_bloqueado_id = c.contacto_id)
               AND NOT EXISTS (SELECT 1 FROM bloqueos b WHERE b.usuario_id = c.contacto_id AND b.usuario_bloqueado_id = c.usuario_id)"
        );
        $stmt->execute([$usuarioId, $usuarioId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function guardarAudienciaYNotificar($estadoId, $usuarioId)
    {
        $destinatarios = $this->destinatariosSegunPrivacidad($usuarioId);
        if (!$destinatarios) return;

        $stmtAudiencia = $this->pdo->prepare(
            "INSERT INTO estado_audiencia (estado_id, usuario_id) VALUES (?, ?)"
        );
        foreach ($destinatarios as $destinatarioId) {
            $stmtAudiencia->execute([$estadoId, $destinatarioId]);
        }

        $stmtNotificaciones = $this->pdo->prepare(
            "INSERT INTO notificaciones
             (usuario_id, remitente_id, tipo, titulo, contenido, referencia_id)
             SELECT ea.usuario_id, actor.id, 'estado',
                    TRIM(CONCAT(actor.nombre, ' ', COALESCE(actor.apellido, ''))),
                    'publicó una nueva historia', ?
             FROM estado_audiencia ea
             INNER JOIN usuarios actor ON actor.id = ?
             LEFT JOIN preferencias_notificaciones pn ON pn.usuario_id = ea.usuario_id
             WHERE ea.estado_id = ? AND COALESCE(pn.estados, 1) = 1"
        );
        $stmtNotificaciones->execute([$estadoId, $usuarioId, $estadoId]);
    }

    public function crearEstado($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token   = $p['token'] ?? '';
        $tipo    = $p['tipo'] ?? '';
        $contenido = trim($p['contenido'] ?? '');
        $archivoId = isset($p['archivo_id']) && $p['archivo_id'] !== '' ? (int)$p['archivo_id'] : null;

        if (!$token || !$tipo) {
            print json_encode(['ok' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!in_array($tipo, ['texto', 'imagen', 'video'], true)) {
            print json_encode(['ok' => false, 'error' => 'Tipo de estado no válido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($tipo === 'texto' && !$contenido) {
            print json_encode(['ok' => false, 'error' => 'El estado de texto no puede estar vacío'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $usuarioId = $this->validarSesion($token);

        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (($p['programar'] ?? '0') === '1') {
            $this->crearEstadoProgramado($p, $tipo, $contenido, (int)$usuarioId);
            return;
        }

        if ($tipo === 'imagen' && !$archivoId && isset($_FILES['archivo'])) {
            $archivoId = $this->subirArchivo($_FILES['archivo'], $this->tiposImagen(), 'uploads/estados/', 10 * 1024 * 1024);
            if (!$archivoId) {
                print json_encode(['ok' => false, 'error' => 'No se pudo subir la imagen'], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        if ($tipo === 'video' && !$archivoId && isset($_FILES['archivo'])) {
            $archivoId = $this->subirArchivo($_FILES['archivo'], $this->tiposVideo(), 'uploads/estados/', 20 * 1024 * 1024);
            if (!$archivoId) {
                print json_encode(['ok' => false, 'error' => 'No se pudo subir el video'], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        if ($tipo !== 'texto' && !$archivoId) {
            print json_encode(['ok' => false, 'error' => 'El archivo es requerido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($archivoId) {
            $sql = "SELECT id FROM archivos WHERE id = ? LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$archivoId]);

            if (!$stmt->fetchColumn()) {
                print json_encode(['ok' => false, 'error' => 'El archivo no existe'], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        try {
            $this->pdo->beginTransaction();
            $sql = "
                INSERT INTO estados
                (usuario_id, tipo, contenido, archivo_id, fecha_creacion, fecha_expiracion, estado)
                VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR), 'activo')
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$usuarioId, $tipo, $contenido ?: null, $archivoId]);
            $estadoId = (int)$this->pdo->lastInsertId();

            $destinatarios = $this->destinatariosSegunPrivacidad($usuarioId);
            if ($destinatarios) {
                $stmtAudiencia = $this->pdo->prepare(
                    "INSERT INTO estado_audiencia (estado_id, usuario_id) VALUES (?, ?)"
                );
                foreach ($destinatarios as $destinatarioId) {
                    $stmtAudiencia->execute([$estadoId, $destinatarioId]);
                }

                $stmtNotificaciones = $this->pdo->prepare(
                    "INSERT INTO notificaciones
                     (usuario_id, remitente_id, tipo, titulo, contenido, referencia_id)
                     SELECT ea.usuario_id, actor.id, 'estado',
                            TRIM(CONCAT(actor.nombre, ' ', COALESCE(actor.apellido, ''))),
                            'publicó una nueva historia', ?
                     FROM estado_audiencia ea
                     INNER JOIN usuarios actor ON actor.id = ?
                     LEFT JOIN preferencias_notificaciones pn ON pn.usuario_id = ea.usuario_id
                     WHERE ea.estado_id = ? AND COALESCE(pn.estados, 1) = 1"
                );
                $stmtNotificaciones->execute([$estadoId, $usuarioId, $estadoId]);
            }

            $this->pdo->commit();
            print json_encode(['ok' => true, 'estado_id' => $estadoId], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('crearEstado: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo guardar el estado y su audiencia'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function crearEstadoProgramado($p, $tipo, $contenido, $usuarioId)
    {
        $fechas = json_decode((string)($p['fechas_programadas'] ?? ''), true);
        $duracion = (int)($p['duracion_horas'] ?? 24);
        $repetir = ($p['repetir_semanal'] ?? '0') === '1';
        $repetirHasta = trim((string)($p['repetir_hasta'] ?? ''));

        if (!is_array($fechas) || count($fechas) < 1 || count($fechas) > 100) {
            print json_encode(['ok' => false, 'error' => 'Selecciona entre 1 y 100 fechas de publicación.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($duracion < 24 || $duracion > 168) {
            print json_encode(['ok' => false, 'error' => 'La duración debe ser de 24 horas a 7 días.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $fechas = array_values(array_unique(array_map('strval', $fechas)));
        sort($fechas);
        $stmtFecha = $this->pdo->prepare("SELECT ? > NOW()");
        foreach ($fechas as $fecha) {
            $formatoValido = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fecha) === 1;
            if (!$formatoValido) {
                print json_encode(['ok' => false, 'error' => 'La fecha programada no es válida.'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $stmtFecha->execute([$fecha]);
            if (!(bool)$stmtFecha->fetchColumn()) {
                print json_encode(['ok' => false, 'error' => 'Elige una fecha y hora futuras.'], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        if ($repetir) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $repetirHasta) || $repetirHasta < substr(end($fechas), 0, 10)) {
                print json_encode(['ok' => false, 'error' => 'Indica hasta qué fecha se repetirá la historia.'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $zona = new DateTimeZone('America/Mexico_City');
            $inicio = new DateTimeImmutable(substr($fechas[0], 0, 10), $zona);
            $fin = new DateTimeImmutable($repetirHasta, $zona);
            if ($fin->diff($inicio)->days > 366) {
                print json_encode(['ok' => false, 'error' => 'La repetición puede programarse hasta por un año.'], JSON_UNESCAPED_UNICODE);
                return;
            }
        } else {
            $repetirHasta = null;
        }

        $archivoId = null;
        $nombreArchivoCreado = null;
        try {
            $this->pdo->beginTransaction();
            if ($tipo === 'imagen') {
                $archivoId = $this->subirArchivo($_FILES['archivo'] ?? [], $this->tiposImagen(), 'uploads/estados/', 10 * 1024 * 1024);
            } elseif ($tipo === 'video') {
                $archivoId = $this->subirArchivo($_FILES['archivo'] ?? [], $this->tiposVideo(), 'uploads/estados/', 20 * 1024 * 1024);
            }
            if ($tipo !== 'texto' && !$archivoId) {
                throw new RuntimeException($tipo === 'imagen' ? 'No se pudo subir la imagen.' : 'No se pudo subir el video.');
            }

            if ($archivoId) {
                $stmtArchivo = $this->pdo->prepare("SELECT nombre_archivo FROM archivos WHERE id = ?");
                $stmtArchivo->execute([$archivoId]);
                $nombreArchivoCreado = $stmtArchivo->fetchColumn() ?: null;
            }

            $insertar = $this->pdo->prepare(
                "INSERT INTO estados_programados
                    (usuario_id, tipo, contenido, archivo_id, programar_en, duracion_horas,
                     repetir_semanal, repetir_hasta, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')"
            );
            foreach ($fechas as $fecha) {
                $insertar->execute([
                    $usuarioId,
                    $tipo,
                    $contenido !== '' ? $contenido : null,
                    $archivoId,
                    $fecha,
                    $duracion,
                    $repetir ? 1 : 0,
                    $repetirHasta,
                ]);
            }
            $this->pdo->commit();
            print json_encode([
                'ok' => true,
                'programadas' => count($fechas),
                'repetir_semanal' => $repetir,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($nombreArchivoCreado) {
                $rutaFisica = dirname(__DIR__) . '/uploads/estados/' . basename($nombreArchivoCreado);
                if (is_file($rutaFisica)) unlink($rutaFisica);
            }
            error_log('crearEstadoProgramado: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo programar la historia.'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function obtenerEstadosProgramados($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->validarSesion($p['token'] ?? '');
        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $stmt = $this->pdo->prepare(
            "SELECT ep.id, ep.tipo, ep.contenido, ep.programar_en, ep.duracion_horas,
                    ep.repetir_semanal, ep.repetir_hasta, a.nombre_original
             FROM estados_programados ep
             LEFT JOIN archivos a ON a.id = ep.archivo_id
             WHERE ep.usuario_id = ? AND ep.estado = 'pendiente'
             ORDER BY ep.programar_en ASC LIMIT 100"
        );
        $stmt->execute([$usuarioId]);
        print json_encode(['ok' => true, 'programados' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
    }

    public function cancelarEstadoProgramado($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->validarSesion($p['token'] ?? '');
        $programadoId = (int)($p['programado_id'] ?? 0);
        if (!$usuarioId || !$programadoId) {
            print json_encode(['ok' => false, 'error' => 'Datos incompletos o sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $nombreArchivo = null;
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                "SELECT archivo_id, estado FROM estados_programados
                 WHERE id = ? AND usuario_id = ? LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$programadoId, $usuarioId]);
            $programado = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$programado || $programado['estado'] !== 'pendiente') {
                $this->pdo->rollBack();
                print json_encode(['ok' => false, 'error' => 'La publicación ya no está pendiente'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $stmt = $this->pdo->prepare("UPDATE estados_programados SET estado = 'cancelada' WHERE id = ?");
            $stmt->execute([$programadoId]);

            $archivoId = (int)($programado['archivo_id'] ?? 0);
            if ($archivoId) {
                $stmt = $this->pdo->prepare(
                    "SELECT a.nombre_archivo,
                        (SELECT COUNT(*) FROM estados WHERE archivo_id = ?) +
                        (SELECT COUNT(*) FROM mensaje_archivos WHERE archivo_id = ?) +
                        (SELECT COUNT(*) FROM estados_programados WHERE archivo_id = ? AND estado = 'pendiente') AS referencias
                     FROM archivos a WHERE a.id = ? LIMIT 1"
                );
                $stmt->execute([$archivoId, $archivoId, $archivoId, $archivoId]);
                $archivo = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($archivo && (int)$archivo['referencias'] === 0) {
                    $nombreArchivo = basename($archivo['nombre_archivo']);
                    $stmt = $this->pdo->prepare("DELETE FROM archivos WHERE id = ?");
                    $stmt->execute([$archivoId]);
                }
            }
            $this->pdo->commit();
            if ($nombreArchivo) {
                $rutaFisica = dirname(__DIR__) . '/uploads/estados/' . $nombreArchivo;
                if (is_file($rutaFisica)) unlink($rutaFisica);
            }
            print json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('cancelarEstadoProgramado: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo cancelar la publicación'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function obtenerMisEstados($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';

        if (!$token) {
            print json_encode(['ok' => false, 'error' => 'Token requerido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $usuarioId = $this->validarSesion($token);

        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sql = "
            SELECT
                e.id,
                e.usuario_id,
                u.nombre,
                u.apellido,
                u.foto,
                e.tipo,
                e.contenido,
                e.archivo_id,
                e.fecha_creacion,
                e.fecha_expiracion,
                e.estado,
                a.nombre_original,
                a.nombre_archivo,
                a.ruta,
                a.tipo_mime,
                a.extension,
                a.tamano,
                (
                    SELECT COUNT(*)
                    FROM estados_vistos ev
                    WHERE ev.estado_id = e.id
                ) AS total_vistas
            FROM estados e
            INNER JOIN usuarios u
                ON u.id = e.usuario_id
            LEFT JOIN archivos a
                ON a.id = e.archivo_id
            WHERE
                e.usuario_id = ?
                AND e.estado = 'activo'
                AND e.fecha_expiracion > NOW()
            ORDER BY e.fecha_creacion DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuarioId]);

        $resultado = $stmt->fetchAll();
        $resultado = array_values(array_filter($resultado, function ($estado) use ($usuarioId) {
            return $this->audienciaPermite($estado['id'], $usuarioId);
        }));

        foreach ($resultado as &$estado) {
            $estado['id'] = (int)$estado['id'];
            $estado['usuario_id'] = (int)$estado['usuario_id'];
            $estado['archivo_id'] = $estado['archivo_id'] !== null ? (int)$estado['archivo_id'] : null;
            $estado['total_vistas'] = (int)$estado['total_vistas'];
            $estado['foto'] = $this->urlRuta($estado['foto']);
            $estado['ruta'] = $this->urlRuta($estado['ruta']);
        }

        print json_encode([
            'ok' => true,
            'usuario_id' => (int)$usuarioId,
            'estados' => $resultado
        ], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerEstados($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';

        if (!$token) {
            print json_encode(['ok' => false, 'error' => 'Token requerido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $usuarioId = $this->validarSesion($token);

        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sql = "
            SELECT
                e.id,
                e.usuario_id,
                u.nombre,
                u.apellido,
                u.foto,
                e.tipo,
                e.contenido,
                e.archivo_id,
                e.fecha_creacion,
                e.fecha_expiracion,
                e.estado,
                a.nombre_original,
                a.nombre_archivo,
                a.ruta,
                a.tipo_mime,
                a.extension,
                a.tamano,
                CASE
                    WHEN ev.id IS NULL THEN 0
                    ELSE 1
                END AS visto
            FROM estados e
            INNER JOIN usuarios u
                ON u.id = e.usuario_id
            LEFT JOIN estados_vistos ev
                ON ev.estado_id = e.id
                AND ev.usuario_id = ?
            LEFT JOIN archivos a
                ON a.id = e.archivo_id
            WHERE
                e.estado = 'activo'
                AND e.fecha_expiracion > NOW()
                AND e.usuario_id != ?
                AND EXISTS (SELECT 1 FROM estado_audiencia ea WHERE ea.estado_id = e.id AND ea.usuario_id = ?)
                AND NOT EXISTS
                (
                    SELECT 1
                    FROM bloqueos b1
                    WHERE
                        b1.usuario_id = ?
                        AND b1.usuario_bloqueado_id = e.usuario_id
                )
                AND NOT EXISTS
                (
                    SELECT 1
                    FROM bloqueos b2
                    WHERE
                        b2.usuario_id = e.usuario_id
                        AND b2.usuario_bloqueado_id = ?
                )
            ORDER BY e.fecha_creacion DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $usuarioId,
            $usuarioId,
            $usuarioId,
            $usuarioId,
            $usuarioId,
        ]);

        $resultado = $stmt->fetchAll();
        $resultado = array_values(array_filter($resultado, function ($estado) use ($usuarioId) {
            return $this->audienciaPermite($estado['id'], $usuarioId);
        }));

        foreach ($resultado as &$estado) {
            $estado['id'] = (int)$estado['id'];
            $estado['usuario_id'] = (int)$estado['usuario_id'];
            $estado['archivo_id'] = $estado['archivo_id'] !== null ? (int)$estado['archivo_id'] : null;
            $estado['visto'] = (int)$estado['visto'];
            if (!$this->privacidadPermite($estado['usuario_id'], $usuarioId, 'foto_perfil')) {
                $estado['foto'] = null;
            }
            $estado['foto'] = $this->urlRuta($estado['foto']);
            $estado['ruta'] = $this->urlRuta($estado['ruta']);
        }

        print json_encode([
            'ok' => true,
            'usuario_id' => (int)$usuarioId,
            'estados' => $resultado
        ], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerEstado($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $estadoId = isset($p['estado_id']) ? (int)$p['estado_id'] : 0;

        if (!$token || !$estadoId) {
            print json_encode(['ok' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $usuarioId = $this->validarSesion($token);

        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sql = "
            SELECT
                e.id,
                e.usuario_id,
                u.nombre,
                u.apellido,
                u.foto,
                e.tipo,
                e.contenido,
                e.archivo_id,
                e.fecha_creacion,
                e.fecha_expiracion,
                e.estado,
                a.nombre_original,
                a.nombre_archivo,
                a.ruta,
                a.tipo_mime,
                a.extension,
                a.tamano
            FROM estados e
            INNER JOIN usuarios u
                ON u.id = e.usuario_id
            LEFT JOIN archivos a
                ON a.id = e.archivo_id
            WHERE
                e.id = ?
                AND e.estado = 'activo'
                AND e.fecha_expiracion > NOW()
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$estadoId]);

        $estado = $stmt->fetch();

        if (!$estado) {
            print json_encode(['ok' => false, 'error' => 'Estado no encontrado o expirado'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$this->audienciaPermite($estado['id'], $usuarioId)) {
            print json_encode(['ok' => false, 'error' => 'No tienes acceso a este estado'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $estado['id'] = (int)$estado['id'];
        $estado['usuario_id'] = (int)$estado['usuario_id'];

        if ($estado['archivo_id'] !== null) {
            $estado['archivo_id'] = (int)$estado['archivo_id'];
        }

        if (!$this->privacidadPermite($estado['usuario_id'], $usuarioId, 'foto_perfil')) {
            $estado['foto'] = null;
        }
        $estado['foto'] = $this->urlRuta($estado['foto']);
        $estado['ruta'] = $this->urlRuta($estado['ruta']);

        print json_encode(['ok' => true, 'estado' => $estado], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerEstadosPorUsuario($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $usuarioIdParam = isset($p['usuario_id']) ? (int)$p['usuario_id'] : 0;

        if (!$token || !$usuarioIdParam) {
            print json_encode(['ok' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $usuarioId = $this->validarSesion($token);

        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ((int)$usuarioIdParam === (int)$usuarioId) {
            $sql = "
                SELECT
                    e.id,
                    e.usuario_id,
                    u.nombre,
                    u.apellido,
                    u.foto,
                    e.tipo,
                    e.contenido,
                    e.archivo_id,
                    e.fecha_creacion,
                    e.fecha_expiracion,
                    e.estado,
                    a.nombre_original,
                    a.nombre_archivo,
                    a.ruta,
                    a.tipo_mime,
                    a.extension,
                    a.tamano,
                    1 AS es_propio,
                    (
                        SELECT COUNT(*)
                        FROM estados_vistos ev
                        WHERE ev.estado_id = e.id
                    ) AS total_vistas
                FROM estados e
                INNER JOIN usuarios u
                    ON u.id = e.usuario_id
                LEFT JOIN archivos a
                    ON a.id = e.archivo_id
                WHERE
                    e.usuario_id = ?
                    AND e.estado = 'activo'
                    AND e.fecha_expiracion > NOW()
                ORDER BY e.fecha_creacion DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$usuarioIdParam]);
        } else {
            $sql = "
                SELECT
                    e.id,
                    e.usuario_id,
                    u.nombre,
                    u.apellido,
                    u.foto,
                    e.tipo,
                    e.contenido,
                    e.archivo_id,
                    e.fecha_creacion,
                    e.fecha_expiracion,
                    e.estado,
                    a.nombre_original,
                    a.nombre_archivo,
                    a.ruta,
                    a.tipo_mime,
                    a.extension,
                    a.tamano,
                    0 AS es_propio,
                    CASE
                        WHEN ev.id IS NULL THEN 0
                        ELSE 1
                    END AS visto
                FROM estados e
                INNER JOIN usuarios u
                    ON u.id = e.usuario_id
                LEFT JOIN estados_vistos ev
                    ON ev.estado_id = e.id
                    AND ev.usuario_id = ?
                LEFT JOIN archivos a
                    ON a.id = e.archivo_id
                WHERE
                    e.usuario_id = ?
                    AND e.estado = 'activo'
                    AND e.fecha_expiracion > NOW()
                    AND EXISTS (SELECT 1 FROM estado_audiencia ea WHERE ea.estado_id = e.id AND ea.usuario_id = ?)
                ORDER BY e.fecha_creacion DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$usuarioId, $usuarioIdParam, $usuarioId]);
        }

        $resultado = $stmt->fetchAll();
        $resultado = array_values(array_filter($resultado, function ($estado) use ($usuarioId) {
            return $this->audienciaPermite($estado['id'], $usuarioId);
        }));

        foreach ($resultado as &$estado) {
            $estado['id'] = (int)$estado['id'];
            $estado['usuario_id'] = (int)$estado['usuario_id'];
            $estado['archivo_id'] = $estado['archivo_id'] !== null ? (int)$estado['archivo_id'] : null;
            $estado['visto'] = isset($estado['visto']) ? (int)$estado['visto'] : 0;
            $estado['es_propio'] = (int)$estado['es_propio'];
            if (!$this->privacidadPermite($estado['usuario_id'], $usuarioId, 'foto_perfil')) {
                $estado['foto'] = null;
            }
            $estado['foto'] = $this->urlRuta($estado['foto']);
            $estado['ruta'] = $this->urlRuta($estado['ruta']);
        }

        print json_encode([
            'ok' => true,
            'estados' => $resultado
        ], JSON_UNESCAPED_UNICODE);
    }

    public function marcarVisto($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $estadoId = isset($p['estado_id']) ? (int)$p['estado_id'] : 0;

        if (!$token || !$estadoId) {
            print json_encode(['ok' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $usuarioId = $this->validarSesion($token);

        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sql = "
            SELECT usuario_id
            FROM estados
            WHERE
                id = ?
                AND estado = 'activo'
                AND fecha_expiracion > NOW()
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$estadoId]);

        $propietarioId = $stmt->fetchColumn();

        if (!$propietarioId) {
            print json_encode(['ok' => false, 'error' => 'Estado no encontrado o expirado'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$this->audienciaPermite($estadoId, $usuarioId)) {
            http_response_code(403);
            print json_encode(['ok' => false, 'error' => 'No tienes permiso para ver este estado'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ((int)$propietarioId === (int)$usuarioId) {
            print json_encode(['ok' => true, 'visto' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sql = "
            INSERT INTO estados_vistos
            (
                estado_id,
                usuario_id,
                fecha_visto
            )
            VALUES
            (
                ?,
                ?,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                fecha_visto = NOW()
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $estadoId,
            $usuarioId
        ]);

        print json_encode(['ok' => true, 'visto' => true], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerVistas($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $estadoId = isset($p['estado_id']) ? (int)$p['estado_id'] : 0;

        if (!$token || !$estadoId) {
            print json_encode(['ok' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $usuarioId = $this->validarSesion($token);

        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sql = "
            SELECT id
            FROM estados
            WHERE
                id = ?
                AND usuario_id = ?
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $estadoId,
            $usuarioId
        ]);

        if (!$stmt->fetchColumn()) {
            print json_encode(['ok' => false, 'error' => 'No tienes permiso para consultar las vistas'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sql = "
            SELECT
                ev.id,
                ev.estado_id,
                ev.usuario_id,
                ev.fecha_visto,
                u.nombre,
                u.apellido,
                u.foto
            FROM estados_vistos ev
            INNER JOIN usuarios u
                ON u.id = ev.usuario_id
            WHERE ev.estado_id = ?
            ORDER BY ev.fecha_visto ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$estadoId]);

        $resultado = $stmt->fetchAll();

        foreach ($resultado as &$vista) {
            $vista['id'] = (int)$vista['id'];
            $vista['usuario_id'] = (int)$vista['usuario_id'];
            if (!$this->privacidadPermite($vista['usuario_id'], $usuarioId, 'foto_perfil')) {
                $vista['foto'] = null;
            }
            $vista['foto'] = $this->urlRuta($vista['foto']);
        }

        print json_encode([
            'ok' => true,
            'vistas' => $resultado
        ], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerEstadosVistos($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';

        if (!$token) {
            print json_encode(['ok' => false, 'error' => 'Token requerido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $usuarioId = $this->validarSesion($token);

        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sql = "
            SELECT
                e.id,
                e.usuario_id,
                u.nombre,
                u.apellido,
                u.foto,
                e.tipo,
                e.contenido,
                e.archivo_id,
                e.fecha_creacion,
                e.fecha_expiracion,
                e.estado,
                a.ruta,
                a.tipo_mime
            FROM estados e
            INNER JOIN estados_vistos ev
                ON ev.estado_id = e.id
            INNER JOIN usuarios u
                ON u.id = e.usuario_id
            LEFT JOIN archivos a
                ON a.id = e.archivo_id
            WHERE
                ev.usuario_id = ?
                AND e.fecha_expiracion > NOW()
            ORDER BY ev.fecha_visto DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuarioId]);

        $resultado = $stmt->fetchAll();

        foreach ($resultado as &$vista) {
            $vista['usuario_id'] = (int)$vista['usuario_id'];
            if (!$this->privacidadPermite($vista['usuario_id'], $usuarioId, 'foto_perfil')) {
                $vista['foto'] = null;
            }
            $vista['foto'] = $this->urlRuta($vista['foto']);
        }
        unset($vista);

        print json_encode([
            'ok' => true,
            'vistas' => $resultado
        ], JSON_UNESCAPED_UNICODE);
    }

    public function eliminarEstado($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $estadoId = isset($p['estado_id']) ? (int)$p['estado_id'] : 0;

        if (!$token || !$estadoId) {
            print json_encode(['ok' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $usuarioId = $this->validarSesion($token);

        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sql = "
            SELECT e.id, e.archivo_id, a.nombre_archivo, a.ruta
            FROM estados e
            LEFT JOIN archivos a
                ON a.id = e.archivo_id
            WHERE
                e.id = ?
                AND e.usuario_id = ?
                AND e.estado = 'activo'
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$estadoId, $usuarioId]);

        $estado = $stmt->fetch();

        if (!$estado) {
            print json_encode(['ok' => false, 'error' => 'No se encontró el estado o no tienes permiso'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->pdo->beginTransaction();

        try {
            $sql = "
                UPDATE estados
                SET estado = 'eliminado'
                WHERE
                    id = ?
                    AND usuario_id = ?
                    AND estado = 'activo'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$estadoId, $usuarioId]);

            $sql = "DELETE FROM estados_vistos WHERE estado_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$estadoId]);

            // Limpia el archivo solo si ningún otro estado lo referencia.
            if (!empty($estado['archivo_id']) && !empty($estado['nombre_archivo'])) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM estados WHERE archivo_id = ? AND id <> ?");
                $stmt->execute([$estado['archivo_id'], $estadoId]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $rutaFisica = dirname(__DIR__) . '/uploads/estados/' . basename($estado['nombre_archivo']);
                    if (is_file($rutaFisica)) unlink($rutaFisica);
                    $stmt = $this->pdo->prepare("DELETE FROM archivos WHERE id = ?");
                    $stmt->execute([$estado['archivo_id']]);
                }
            }

            $this->pdo->commit();

            print json_encode([
                'ok' => true,
                'mensaje' => 'Estado eliminado correctamente'
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $ex) {
            $this->pdo->rollBack();
            print json_encode([
                'ok' => false,
                'error' => 'Error al eliminar el estado: ' . $ex->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
