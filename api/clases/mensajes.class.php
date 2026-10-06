<?php

class mensajes
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    private function validarSesion($token)
    {
        $sql = "
            SELECT usuario_id
            FROM sesiones
            WHERE token = ?
            AND estado = 'activa'
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$token]);

        return $stmt->fetchColumn();
    }

    public function enviarMensaje($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $conversacionId = isset($p['conversacion_id']) ? (int)$p['conversacion_id'] : 0;
        $mensajeRecibido = $p['mensaje'] ?? '';
        $mensaje = is_string($mensajeRecibido) ? trim($mensajeRecibido) : '';

        if (!$token || !$conversacionId || $mensaje === '') {
            print json_encode([
                'ok' => false,
                'error' => 'Datos incompletos'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $longitudMensaje = function_exists('mb_strlen')
            ? mb_strlen($mensaje, 'UTF-8')
            : strlen($mensaje);

        if ($longitudMensaje > 4000) {
            print json_encode([
                'ok' => false,
                'error' => 'El mensaje no puede superar los 4000 caracteres'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $usuarioId = $this->validarSesion($token);

        if (!$usuarioId) {
            print json_encode([
                'ok' => false,
                'error' => 'Sesión no válida'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
            SELECT id
            FROM conversaciones
            WHERE id = ?
            AND estado = 'activa'
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $conversacionId
        ]);

        if (!$stmt->fetchColumn()) {
            print json_encode([
                'ok' => false,
                'error' => 'La conversación no existe'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
            SELECT id
            FROM conversacion_usuarios
            WHERE conversacion_id = ?
            AND usuario_id = ?
            AND estado = 'activo'
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $conversacionId,
            $usuarioId
        ]);

        if (!$stmt->fetchColumn()) {
            print json_encode([
                'ok' => false,
                'error' => 'No perteneces a esta conversación'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        try {
            $this->pdo->beginTransaction();

            $sql = "
                INSERT INTO mensajes
                (
                    conversacion_id,
                    remitente_id,
                    mensaje,
                    mensaje_tipo,
                    estado
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    'texto',
                    'activo'
                )
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $conversacionId,
                $usuarioId,
                $mensaje
            ]);

            $mensajeId = $this->pdo->lastInsertId();

            $sql = "
                INSERT INTO mensaje_destinatarios
                (
                    mensaje_id,
                    usuario_id,
                    estado
                )
                SELECT
                    ?,
                    usuario_id,
                    'enviado'
                FROM conversacion_usuarios
                WHERE conversacion_id = ?
                AND usuario_id <> ?
                AND estado = 'activo'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $mensajeId,
                $conversacionId,
                $usuarioId
            ]);

            $sql = "
                INSERT INTO notificaciones
                (usuario_id, remitente_id, tipo, titulo, contenido, referencia_id)
                SELECT
                    md.usuario_id,
                    actor.id,
                    CASE WHEN c.tipo = 'grupo' THEN 'grupo' ELSE 'privado' END,
                    CASE
                        WHEN c.tipo = 'grupo' THEN COALESCE(NULLIF(c.nombre, ''), 'Grupo')
                        ELSE TRIM(CONCAT(actor.nombre, ' ', COALESCE(actor.apellido, '')))
                    END,
                    CASE
                        WHEN c.tipo = 'grupo' THEN CONCAT(
                            TRIM(CONCAT(actor.nombre, ' ', COALESCE(actor.apellido, ''))),
                            ': ', LEFT(?, 400)
                        )
                        ELSE LEFT(?, 500)
                    END,
                    c.id
                FROM mensaje_destinatarios md
                INNER JOIN conversaciones c ON c.id = ?
                INNER JOIN usuarios actor ON actor.id = ?
                LEFT JOIN preferencias_notificaciones pn ON pn.usuario_id = md.usuario_id
                WHERE md.mensaje_id = ?
                    AND ((c.tipo = 'grupo' AND COALESCE(pn.grupos, 1) = 1)
                      OR (c.tipo <> 'grupo' AND COALESCE(pn.privados, 1) = 1))
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $mensaje,
                $mensaje,
                $conversacionId,
                $usuarioId,
                $mensajeId
            ]);

            $sql = "
                UPDATE conversaciones
                SET fecha_actualizacion = NOW()
                WHERE id = ?
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $conversacionId
            ]);

            $this->pdo->commit();

            print json_encode([
                'ok' => true,
                'mensaje_id' => $mensajeId
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            print json_encode([
                'ok' => false,
                'error' => 'No se pudo enviar el mensaje'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function enviarArchivo($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $token = $p['token'] ?? '';
        $conversacionId = (int)($p['conversacion_id'] ?? 0);
        $usuarioId = $this->validarSesion($token);
        if (!$usuarioId || !$conversacionId || empty($_FILES['archivo'])) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'Falta la sesión, conversación o archivo'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM conversaciones c
             INNER JOIN conversacion_usuarios cu ON cu.conversacion_id = c.id
             WHERE c.id = ? AND c.tipo = 'privada' AND c.estado = 'activa'
               AND cu.usuario_id = ? AND cu.estado = 'activo' LIMIT 1"
        );
        $stmt->execute([$conversacionId, $usuarioId]);
        if (!$stmt->fetchColumn()) {
            http_response_code(403);
            print json_encode(['ok' => false, 'error' => 'Solo puedes enviar archivos en un chat privado activo'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $archivo = $_FILES['archivo'];
        $errorSubida = (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorSubida !== UPLOAD_ERR_OK) {
            $mensajeError = $errorSubida === UPLOAD_ERR_INI_SIZE || $errorSubida === UPLOAD_ERR_FORM_SIZE
                ? 'El archivo supera el límite configurado en PHP (' . (ini_get('upload_max_filesize') ?: 'desconocido') . ').'
                : 'No se recibió un archivo válido. Código de subida: ' . $errorSubida;
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => $mensajeError], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (empty($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'No se recibió un archivo válido.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $mimeDetectado = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        $mimeDeclarado = strtolower((string)($archivo['type'] ?? ''));
        // Some PHP/fileinfo builds classify audio-only MP4/WebM containers as video.
        $mime = in_array($mimeDetectado, ['video/webm', 'video/mp4'], true)
            && in_array($mimeDeclarado, ['audio/webm', 'audio/mp4'], true)
            ? $mimeDeclarado
            : $mimeDetectado;
        $tipos = [
            'image/jpeg' => ['jpg', 'imagen', 10 * 1024 * 1024],
            'image/png' => ['png', 'imagen', 10 * 1024 * 1024],
            'image/webp' => ['webp', 'imagen', 10 * 1024 * 1024],
            'image/gif' => ['gif', 'imagen', 10 * 1024 * 1024],
            'image/avif' => ['avif', 'imagen', 10 * 1024 * 1024],
            'audio/mpeg' => ['mp3', 'audio', 20 * 1024 * 1024],
            'audio/mp4' => ['m4a', 'audio', 20 * 1024 * 1024],
            'audio/aac' => ['aac', 'audio', 20 * 1024 * 1024],
            'audio/ogg' => ['ogg', 'audio', 20 * 1024 * 1024],
            'audio/webm' => ['webm', 'audio', 20 * 1024 * 1024],
            'audio/wav' => ['wav', 'audio', 20 * 1024 * 1024],
            'audio/x-wav' => ['wav', 'audio', 20 * 1024 * 1024],
            'audio/3gpp' => ['3gp', 'audio', 20 * 1024 * 1024],
        ];
        if (!isset($tipos[$mime]) || $archivo['size'] <= 0 || $archivo['size'] > $tipos[$mime][2]) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'Elige una imagen de hasta 10 MB o un audio de hasta 20 MB'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $tipos[$mime][0];
        $carpeta = dirname(__DIR__) . '/uploads/mensajes/';
        if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true)) {
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo preparar el almacenamiento'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $rutaFisica = $carpeta . $nombreArchivo;
        if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) {
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo guardar el archivo'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $this->pdo->beginTransaction();
            $ruta = '/new%20app/api/uploads/mensajes/' . $nombreArchivo;
            $stmt = $this->pdo->prepare(
                "INSERT INTO archivos (nombre_original, nombre_archivo, ruta, tipo_mime, extension, tamano, hash_archivo, fecha_creacion)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                mb_substr((string)$archivo['name'], 0, 255), $nombreArchivo, $ruta, $mime,
                $tipos[$mime][0], $archivo['size'], hash_file('sha256', $rutaFisica),
            ]);
            $archivoId = (int)$this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare(
                "INSERT INTO mensajes (conversacion_id, remitente_id, mensaje, mensaje_tipo, estado)
                 VALUES (?, ?, ?, ?, 'activo')"
            );
            $etiqueta = $tipos[$mime][1] === 'imagen' ? 'Foto' : 'Audio';
            $stmt->execute([$conversacionId, $usuarioId, $etiqueta, $tipos[$mime][1]]);
            $mensajeId = (int)$this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare("INSERT INTO mensaje_archivos (mensaje_id, archivo_id) VALUES (?, ?)");
            $stmt->execute([$mensajeId, $archivoId]);

            $stmt = $this->pdo->prepare(
                "INSERT INTO mensaje_destinatarios (mensaje_id, usuario_id, estado)
                 SELECT ?, usuario_id, 'enviado' FROM conversacion_usuarios
                 WHERE conversacion_id = ? AND usuario_id <> ? AND estado = 'activo'"
            );
            $stmt->execute([$mensajeId, $conversacionId, $usuarioId]);

            $stmt = $this->pdo->prepare(
                "INSERT INTO notificaciones (usuario_id, remitente_id, tipo, titulo, contenido, referencia_id)
                 SELECT md.usuario_id, actor.id, 'privado', TRIM(CONCAT(actor.nombre, ' ', COALESCE(actor.apellido, ''))), ?, c.id
                 FROM mensaje_destinatarios md
                 INNER JOIN conversaciones c ON c.id = ?
                 INNER JOIN usuarios actor ON actor.id = ?
                 LEFT JOIN preferencias_notificaciones pn ON pn.usuario_id = md.usuario_id
                 WHERE md.mensaje_id = ? AND COALESCE(pn.privados, 1) = 1"
            );
            $stmt->execute([$etiqueta === 'Foto' ? '📷 Te envió una foto' : '🎤 Te envió un audio', $conversacionId, $usuarioId, $mensajeId]);
            $stmt = $this->pdo->prepare("UPDATE conversaciones SET fecha_actualizacion = NOW() WHERE id = ?");
            $stmt->execute([$conversacionId]);
            $this->pdo->commit();

            print json_encode(['ok' => true, 'mensaje_id' => $mensajeId], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if (is_file($rutaFisica)) unlink($rutaFisica);
            error_log('enviarArchivo: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo enviar el archivo'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function eliminarMensajeParaTodos($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->validarSesion($p['token'] ?? '');
        $mensajeId = (int)($p['mensaje_id'] ?? 0);
        if (!$usuarioId || !$mensajeId) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $nombreArchivo = null;
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                "SELECT m.remitente_id, m.conversacion_id, m.estado, ma.archivo_id, a.nombre_archivo
                 FROM mensajes m
                 INNER JOIN conversaciones c ON c.id = m.conversacion_id AND c.tipo = 'privada'
                 INNER JOIN conversacion_usuarios cu ON cu.conversacion_id = c.id AND cu.usuario_id = ? AND cu.estado = 'activo'
                 LEFT JOIN mensaje_archivos ma ON ma.mensaje_id = m.id
                 LEFT JOIN archivos a ON a.id = ma.archivo_id
                 WHERE m.id = ? LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$usuarioId, $mensajeId]);
            $mensaje = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$mensaje || (int)$mensaje['remitente_id'] !== (int)$usuarioId) {
                $this->pdo->rollBack();
                http_response_code(403);
                print json_encode(['ok' => false, 'error' => 'Solo puedes eliminar tus propios mensajes del chat privado'], JSON_UNESCAPED_UNICODE);
                return;
            }
            if ($mensaje['estado'] === 'eliminado') {
                $this->pdo->commit();
                print json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
                return;
            }

            $archivoId = $mensaje['archivo_id'] ? (int)$mensaje['archivo_id'] : 0;
            $nombreArchivo = $mensaje['nombre_archivo'] ? basename($mensaje['nombre_archivo']) : null;
            $stmt = $this->pdo->prepare("UPDATE mensajes SET mensaje = 'Este mensaje fue eliminado', estado = 'eliminado' WHERE id = ?");
            $stmt->execute([$mensajeId]);
            if ($archivoId) {
                $stmt = $this->pdo->prepare("DELETE FROM mensaje_archivos WHERE mensaje_id = ? AND archivo_id = ?");
                $stmt->execute([$mensajeId, $archivoId]);
                $stmt = $this->pdo->prepare(
                    "SELECT (SELECT COUNT(*) FROM estados WHERE archivo_id = ?) + (SELECT COUNT(*) FROM mensaje_archivos WHERE archivo_id = ?)"
                );
                $stmt->execute([$archivoId, $archivoId]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $stmt = $this->pdo->prepare("DELETE FROM archivos WHERE id = ?");
                    $stmt->execute([$archivoId]);
                } else {
                    $nombreArchivo = null;
                }
            }
            $this->pdo->commit();
            if ($nombreArchivo) {
                $rutaFisica = dirname(__DIR__) . '/uploads/mensajes/' . $nombreArchivo;
                if (is_file($rutaFisica)) unlink($rutaFisica);
            }
            print json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('eliminarMensajeParaTodos: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo eliminar el mensaje'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function obtenerArchivoAdjunto($p, $g)
    {
        $usuarioId = $this->validarSesion($p['token'] ?? '');
        $mensajeId = (int)($p['mensaje_id'] ?? 0);
        if (!$usuarioId || !$mensajeId) {
            http_response_code(401);
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $stmt = $this->pdo->prepare(
            "SELECT a.nombre_archivo, a.tipo_mime
             FROM mensajes m
             INNER JOIN conversacion_usuarios cu ON cu.conversacion_id = m.conversacion_id AND cu.usuario_id = ? AND cu.estado = 'activo'
             INNER JOIN mensaje_archivos ma ON ma.mensaje_id = m.id
             INNER JOIN archivos a ON a.id = ma.archivo_id
             WHERE m.id = ? AND m.estado = 'activo' LIMIT 1"
        );
        $stmt->execute([$usuarioId, $mensajeId]);
        $archivo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$archivo) {
            http_response_code(404);
            print json_encode(['ok' => false, 'error' => 'El archivo ya no existe o no tienes acceso'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $nombreArchivo = basename($archivo['nombre_archivo']);
        $rutaFisica = dirname(__DIR__) . '/uploads/mensajes/' . $nombreArchivo;
        if (!is_file($rutaFisica)) {
            http_response_code(404);
            print json_encode(['ok' => false, 'error' => 'No se encontró el archivo'], JSON_UNESCAPED_UNICODE);
            return;
        }

        header('Content-Type: ' . $archivo['tipo_mime']);
        header('Content-Length: ' . filesize($rutaFisica));
        header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        readfile($rutaFisica);
    }

    public function obtenerMensajes($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $conversacionId = isset($p['conversacion_id']) ? (int)$p['conversacion_id'] : 0;
        $ultimoId = isset($p['ultimo_id']) ? (int)$p['ultimo_id'] : 0;
        $limite = 100; // carga inicial

        if (!$token || !$conversacionId) {
            print json_encode([
                'ok' => false,
                'error' => 'Datos incompletos'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $usuarioId = $this->validarSesion($token);

        if (!$usuarioId) {
            print json_encode([
                'ok' => false,
                'error' => 'Sesión no válida'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
            SELECT id
            FROM conversacion_usuarios
            WHERE conversacion_id = ?
            AND usuario_id = ?
            AND estado = 'activo'
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $conversacionId,
            $usuarioId
        ]);

        if (!$stmt->fetchColumn()) {
            print json_encode([
                'ok' => false,
                'error' => 'No perteneces a esta conversación'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        // Marca como leídos los mensajes que le llegaron a este usuario
        $sql = "
            UPDATE mensaje_destinatarios md
            INNER JOIN mensajes m
                ON m.id = md.mensaje_id
            SET
                md.estado = 'leido',
                md.fecha_leido = NOW()
            WHERE md.usuario_id = ?
            AND m.conversacion_id = ?
            AND md.estado <> 'leido'
            AND m.estado <> 'eliminado'
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $usuarioId,
            $conversacionId
        ]);

        // Base del SELECT (estado calculado solo para mis mensajes)
        $base = "
            SELECT
                m.id,
                m.conversacion_id,
                m.remitente_id,
                m.mensaje,
                CASE WHEN m.estado = 'eliminado' THEN 'eliminado' ELSE m.mensaje_tipo END AS mensaje_tipo,
                CASE WHEN m.estado = 'eliminado' THEN NULL ELSE CONCAT('/new%20app/api/controladores/mensajes_controlador.php?task=obtenerArchivo&mensaje_id=', m.id) END AS archivo_url,
                CASE WHEN m.estado = 'eliminado' THEN NULL ELSE a.tipo_mime END AS archivo_mime,
                CASE WHEN m.estado = 'eliminado' THEN NULL ELSE a.nombre_original END AS archivo_nombre,
                m.fecha_envio,
                u.nombre,
                u.apellido,
                CASE
                    WHEN m.remitente_id = ? THEN
                        CASE
                            WHEN md.total IS NULL THEN 'enviado'
                            WHEN md.leidos = md.total THEN 'leido'
                            WHEN md.leidos + md.entregados = md.total THEN 'entregado'
                            ELSE 'enviado'
                        END
                    ELSE NULL
                END AS estado_mensaje
            FROM mensajes m
            INNER JOIN usuarios u
                ON u.id = m.remitente_id
            LEFT JOIN
            (
                SELECT
                    md2.mensaje_id,
                    COUNT(*) AS total,
                    SUM(md2.estado = 'leido') AS leidos,
                    SUM(md2.estado = 'entregado') AS entregados
                FROM mensaje_destinatarios md2
                INNER JOIN mensajes m2
                    ON m2.id = md2.mensaje_id
                WHERE m2.conversacion_id = ?
                GROUP BY md2.mensaje_id
            ) md
                ON md.mensaje_id = m.id
            LEFT JOIN mensaje_archivos ma ON ma.mensaje_id = m.id
            LEFT JOIN archivos a ON a.id = ma.archivo_id
            WHERE m.conversacion_id = ?
        ";

        if ($ultimoId > 0) {
            // Solo lo nuevo
            $sql = $base . " AND m.id > ? ORDER BY m.id ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $usuarioId,
                $conversacionId,
                $conversacionId,
                $ultimoId
            ]);

            $mensajes = $stmt->fetchAll();
        } else {
            // Carga inicial: los últimos N, en orden ascendente
            $sql = "
                SELECT t.*
                FROM (
                    " . $base . "
                    ORDER BY m.id DESC
                    LIMIT " . (int)$limite . "
                ) t
                ORDER BY t.id ASC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $usuarioId,
                $conversacionId,
                $conversacionId
            ]);

            $mensajes = $stmt->fetchAll();
        }

        // Estado actualizado de mis mensajes que el cliente aún tiene como no leídos
        $estados = [];

        $pendientes = array_filter(
            array_map('intval', explode(',', (string)($p['pendientes'] ?? ''))),
            function ($id) {
                return $id > 0;
            }
        );

        $pendientes = array_slice(array_values(array_unique($pendientes)), 0, 200);

        if ($pendientes) {
            $placeholders = implode(',', array_fill(0, count($pendientes), '?'));

            $sql = "
                SELECT
                    md2.mensaje_id AS id,
                    CASE
                        WHEN SUM(md2.estado = 'leido') = COUNT(*) THEN 'leido'
                        WHEN SUM(md2.estado IN ('leido', 'entregado')) = COUNT(*) THEN 'entregado'
                        ELSE 'enviado'
                    END AS estado_mensaje
                FROM mensaje_destinatarios md2
                INNER JOIN mensajes m2
                    ON m2.id = md2.mensaje_id
                WHERE m2.conversacion_id = ?
                AND m2.remitente_id = ?
                AND m2.id IN ($placeholders)
                GROUP BY md2.mensaje_id
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge([$conversacionId, $usuarioId], $pendientes));

            $estados = $stmt->fetchAll();
        }

        $stmt = $this->pdo->prepare("SELECT id FROM mensajes WHERE conversacion_id = ? AND estado = 'eliminado'");
        $stmt->execute([$conversacionId]);
        $eliminados = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        print json_encode([
            'ok' => true,
            'mensajes' => $mensajes,
            'estados' => $estados,
            'eliminados' => $eliminados,
        ], JSON_UNESCAPED_UNICODE);
    }
}
