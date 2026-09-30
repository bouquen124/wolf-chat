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

        if (!$token || !$conversacionId || $mensaje === '')
        {
            print json_encode([
                'ok' => false,
                'error' => 'Datos incompletos'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $usuarioId = $this->validarSesion($token);

        if (!$usuarioId)
        {
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

        if (!$stmt->fetchColumn())
        {
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

        if (!$stmt->fetchColumn())
        {
            print json_encode([
                'ok' => false,
                'error' => 'No perteneces a esta conversación'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

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

        print json_encode([
            'ok' => true,
            'mensaje_id' => $mensajeId
        ], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerMensajes($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $conversacionId = isset($p['conversacion_id']) ? (int)$p['conversacion_id'] : 0;

        if (!$token || !$conversacionId)
        {
            print json_encode([
                'ok' => false,
                'error' => 'Datos incompletos'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $usuarioId = $this->validarSesion($token);

        if (!$usuarioId)
        {
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

        if (!$stmt->fetchColumn())
        {
            print json_encode([
                'ok' => false,
                'error' => 'No perteneces a esta conversación'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
            SELECT
                m.id,
                m.conversacion_id,
                m.remitente_id,
                m.mensaje,
                m.mensaje_tipo,
                m.fecha_envio,
                u.nombre,
                u.apellido
            FROM mensajes m
            INNER JOIN usuarios u
                ON u.id = m.remitente_id
            WHERE m.conversacion_id = ?
            AND m.estado <> 'eliminado'
            ORDER BY m.id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $conversacionId
        ]);

        print json_encode([
            'ok' => true,
            'mensajes' => $stmt->fetchAll()
        ], JSON_UNESCAPED_UNICODE);
    }
}