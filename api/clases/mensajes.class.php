
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
        $mensaje = trim($p['mensaje'] ?? '');

        if (!$token || !$conversacionId || $mensaje === '') {
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
                m.mensaje_tipo,
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
            WHERE m.conversacion_id = ?
            AND m.estado <> 'eliminado'
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

        print json_encode([
            'ok' => true,
            'mensajes' => $mensajes,
            'estados' => $estados
        ], JSON_UNESCAPED_UNICODE);
    }
}
