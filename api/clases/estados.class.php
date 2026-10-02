<?php
class estados
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->limpiarEstadosVencidos();
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
                        (SELECT COUNT(*) FROM mensaje_archivos WHERE archivo_id = ?)"
                );
                $stmt->execute([$archivoId, $archivoId]);

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

    private function urlRuta($ruta)
    {
        if (empty($ruta)) {
            return '';
        }
        if (strpos($ruta, 'http://') === 0 || strpos($ruta, 'https://') === 0) {
            return $ruta;
        }
        return 'http://localhost' . $ruta;
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

        $sql = "
            INSERT INTO estados
            (
                usuario_id,
                tipo,
                contenido,
                archivo_id,
                fecha_creacion,
                fecha_expiracion,
                estado
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                NOW(),
                DATE_ADD(NOW(), INTERVAL 24 HOUR),
                'activo'
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $usuarioId,
            $tipo,
            $contenido ?: null,
            $archivoId
        ]);

        print json_encode([
            'ok' => true,
            'estado_id' => (int)$this->pdo->lastInsertId()
        ], JSON_UNESCAPED_UNICODE);
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
                AND EXISTS
                (
                    SELECT 1
                    FROM contactos c
                    WHERE
                        c.usuario_id = ?
                        AND c.contacto_id = e.usuario_id
                )
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
            $usuarioId
        ]);

        $resultado = $stmt->fetchAll();

        foreach ($resultado as &$estado) {
            $estado['id'] = (int)$estado['id'];
            $estado['usuario_id'] = (int)$estado['usuario_id'];
            $estado['archivo_id'] = $estado['archivo_id'] !== null ? (int)$estado['archivo_id'] : null;
            $estado['visto'] = (int)$estado['visto'];
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

        if ((int)$estado['usuario_id'] !== (int)$usuarioId) {
            $sql = "
                SELECT 1
                FROM contactos c
                WHERE
                    c.usuario_id = ?
                    AND c.contacto_id = ?
                    AND NOT EXISTS
                    (
                        SELECT 1
                        FROM bloqueos b1
                        WHERE
                            b1.usuario_id = ?
                            AND b1.usuario_bloqueado_id = ?
                    )
                    AND NOT EXISTS
                    (
                        SELECT 1
                        FROM bloqueos b2
                        WHERE
                            b2.usuario_id = ?
                            AND b2.usuario_bloqueado_id = ?
                    )
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $usuarioId,
                $estado['usuario_id'],
                $usuarioId,
                $estado['usuario_id'],
                $estado['usuario_id'],
                $usuarioId
            ]);

            if (!$stmt->fetchColumn()) {
                print json_encode(['ok' => false, 'error' => 'No tienes acceso a este estado'], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        $estado['id'] = (int)$estado['id'];
        $estado['usuario_id'] = (int)$estado['usuario_id'];

        if ($estado['archivo_id'] !== null) {
            $estado['archivo_id'] = (int)$estado['archivo_id'];
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
                    AND EXISTS
                    (
                        SELECT 1
                        FROM contactos c
                        WHERE
                            c.usuario_id = ?
                            AND c.contacto_id = e.usuario_id
                    )
                ORDER BY e.fecha_creacion DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$usuarioId, $usuarioIdParam, $usuarioId]);
        }

        $resultado = $stmt->fetchAll();

        foreach ($resultado as &$estado) {
            $estado['id'] = (int)$estado['id'];
            $estado['usuario_id'] = (int)$estado['usuario_id'];
            $estado['archivo_id'] = $estado['archivo_id'] !== null ? (int)$estado['archivo_id'] : null;
            $estado['visto'] = isset($estado['visto']) ? (int)$estado['visto'] : 0;
            $estado['es_propio'] = (int)$estado['es_propio'];
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
