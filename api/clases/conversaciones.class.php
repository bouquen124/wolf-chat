
<?php

class conversaciones
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

    public function crearConversacion($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $destinatarioId = isset($p['destinatario_id']) ? (int)$p['destinatario_id'] : 0;

        if (!$token || !$destinatarioId) {
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

        if ((int)$usuarioId === $destinatarioId) {
            print json_encode([
                'ok' => false,
                'error' => 'No puedes crear una conversación contigo mismo'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
            SELECT id
            FROM usuarios
            WHERE id = ?
            AND estado = 'activo'
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$destinatarioId]);

        if (!$stmt->fetchColumn()) {
            print json_encode([
                'ok' => false,
                'error' => 'El usuario no existe'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
            SELECT c.id
            FROM conversaciones c
            INNER JOIN conversacion_usuarios cu1
                ON cu1.conversacion_id = c.id
            INNER JOIN conversacion_usuarios cu2
                ON cu2.conversacion_id = c.id
            WHERE c.tipo = 'privada'
            AND c.estado = 'activa'
            AND cu1.usuario_id = ?
            AND cu1.estado = 'activo'
            AND cu2.usuario_id = ?
            AND cu2.estado = 'activo'
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $usuarioId,
            $destinatarioId
        ]);

        $conversacionId = $stmt->fetchColumn();

        if ($conversacionId) {
            print json_encode([
                'ok' => true,
                'existe' => true,
                'conversacion_id' => $conversacionId
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
            INSERT INTO conversaciones
            (
                tipo,
                creador_id,
                estado
            )
            VALUES
            (
                'privada',
                ?,
                'activa'
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $usuarioId
        ]);

        $conversacionId = $this->pdo->lastInsertId();

        $sql = "
            INSERT INTO conversacion_usuarios
            (
                conversacion_id,
                usuario_id,
                rol,
                estado
            )
            VALUES
            (
                ?,
                ?,
                'miembro',
                'activo'
            ),
            (
                ?,
                ?,
                'miembro',
                'activo'
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $conversacionId,
            $usuarioId,
            $conversacionId,
            $destinatarioId
        ]);

        print json_encode([
            'ok' => true,
            'existe' => false,
            'conversacion_id' => $conversacionId
        ], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerConversacionPrivada($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $destinatarioId = isset($p['destinatario_id']) ? (int)$p['destinatario_id'] : 0;

        if (!$token || !$destinatarioId) {
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
            SELECT c.id
            FROM conversaciones c
            INNER JOIN conversacion_usuarios cu1
                ON cu1.conversacion_id = c.id
            INNER JOIN conversacion_usuarios cu2
                ON cu2.conversacion_id = c.id
            WHERE c.tipo = 'privada'
            AND c.estado = 'activa'
            AND cu1.usuario_id = ?
            AND cu1.estado = 'activo'
            AND cu2.usuario_id = ?
            AND cu2.estado = 'activo'
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $usuarioId,
            $destinatarioId
        ]);

        $conversacionId = $stmt->fetchColumn();

        if (!$conversacionId) {
            print json_encode([
                'ok' => true,
                'existe' => false
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        print json_encode([
            'ok' => true,
            'existe' => true,
            'conversacion_id' => $conversacionId
        ], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerContacto($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $conversacionId = isset($p['conversacion_id']) ? (int)$p['conversacion_id'] : 0;

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
            SELECT
                u.id,
                u.nombre,
                u.apellido,
                u.foto
            FROM conversacion_usuarios cu
            INNER JOIN usuarios u
                ON u.id = cu.usuario_id
            WHERE cu.conversacion_id = ?
            AND cu.usuario_id <> ?
            AND cu.estado = 'activo'
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $conversacionId,
            $usuarioId
        ]);

        $contacto = $stmt->fetch();

        if (!$contacto) {
            print json_encode([
                'ok' => false,
                'error' => 'No se encontró el contacto'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        print json_encode([
            'ok' => true,
            'contacto' => $contacto
        ], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerChats($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';

        if (!$token) {
            print json_encode([
                'ok' => false,
                'error' => 'Token requerido'
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
        SELECT
            chats.contacto_id,
            u.nombre,
            u.apellido,
            u.foto,
            chats.conversacion_id,
            ultimo_mensaje.mensaje,
            ultimo_mensaje.mensaje_tipo,
            ultimo_mensaje.fecha_envio,
            COALESCE(pendientes.no_leidos, 0) AS no_leidos

        FROM
        (
            SELECT
                ct.contacto_id,

                (
                    SELECT c.id
                    FROM conversaciones c
                    INNER JOIN conversacion_usuarios cu1
                        ON cu1.conversacion_id = c.id
                    INNER JOIN conversacion_usuarios cu2
                        ON cu2.conversacion_id = c.id
                    WHERE c.tipo = 'privada'
                    AND c.estado = 'activa'
                    AND cu1.usuario_id = ?
                    AND cu1.estado = 'activo'
                    AND cu2.usuario_id = ct.contacto_id
                    AND cu2.estado = 'activo'
                    LIMIT 1
                ) AS conversacion_id

            FROM contactos ct

            WHERE ct.usuario_id = ?

            UNION

            SELECT
                cu2.usuario_id AS contacto_id,
                c.id AS conversacion_id

            FROM conversaciones c

            INNER JOIN conversacion_usuarios cu1
                ON cu1.conversacion_id = c.id

            INNER JOIN conversacion_usuarios cu2
                ON cu2.conversacion_id = c.id

            WHERE c.tipo = 'privada'
            AND c.estado = 'activa'
            AND cu1.usuario_id = ?
            AND cu1.estado = 'activo'
            AND cu2.usuario_id <> ?
            AND cu2.estado = 'activo'
        ) chats

        INNER JOIN usuarios u
            ON u.id = chats.contacto_id

        LEFT JOIN
        (
            SELECT
                m1.conversacion_id,
                m1.mensaje,
                m1.mensaje_tipo,
                m1.fecha_envio

            FROM mensajes m1

            INNER JOIN
            (
                SELECT
                    conversacion_id,
                    MAX(id) AS id

                FROM mensajes

                WHERE estado <> 'eliminado'

                GROUP BY conversacion_id
            ) ultimo

                ON ultimo.conversacion_id = m1.conversacion_id
                AND ultimo.id = m1.id

            WHERE m1.estado <> 'eliminado'

        ) ultimo_mensaje

            ON ultimo_mensaje.conversacion_id = chats.conversacion_id

        LEFT JOIN
        (
            SELECT
                md.usuario_id,
                m.conversacion_id,
                COUNT(*) AS no_leidos

            FROM mensaje_destinatarios md

            INNER JOIN mensajes m
                ON m.id = md.mensaje_id

            WHERE md.estado <> 'leido'
            AND m.estado <> 'eliminado'

            GROUP BY
                md.usuario_id,
                m.conversacion_id

        ) pendientes

            ON pendientes.usuario_id = ?
            AND pendientes.conversacion_id = chats.conversacion_id

        WHERE u.estado = 'activo'

        ORDER BY
            CASE
                WHEN ultimo_mensaje.fecha_envio IS NULL THEN 1
                ELSE 0
            END,
            ultimo_mensaje.fecha_envio DESC,
            u.nombre,
            u.apellido
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

        foreach ($resultado as &$chat) {
            $chat['no_leidos'] = (int)($chat['no_leidos'] ?? 0);
        }

        print json_encode([
            'ok' => true,
            'chats' => $resultado
        ], JSON_UNESCAPED_UNICODE);
    }
}
