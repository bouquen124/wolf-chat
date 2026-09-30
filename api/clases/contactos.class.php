<?php

class contactos
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
    public function agregarContacto($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $contactoId = isset($p['contacto_id']) ? (int)$p['contacto_id'] : 0;

        if (!$token || !$contactoId) {
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

        if ((int)$usuarioId === $contactoId) {
            print json_encode([
                'ok' => false,
                'error' => 'No puedes agregarte a ti mismo'
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
        $stmt->execute([$contactoId]);

        if (!$stmt->fetchColumn()) {
            print json_encode([
                'ok' => false,
                'error' => 'El usuario no existe'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
            SELECT id
            FROM contactos
            WHERE usuario_id = ?
            AND contacto_id = ?
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuarioId, $contactoId]);

        if ($stmt->fetchColumn()) {
            print json_encode([
                'ok' => false,
                'error' => 'El contacto ya está agregado'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
            INSERT INTO contactos
            (
                usuario_id,
                contacto_id
            )
            VALUES
            (
                ?,
                ?
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $usuarioId,
            $contactoId
        ]);

        print json_encode([
            'ok' => true,
            'id' => $this->pdo->lastInsertId()
        ], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerContactos($p, $g)
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
                c.id,
                c.contacto_id,
                c.nombre_personalizado,
                u.telefono,
                u.nombre,
                u.apellido,
                u.foto
            FROM contactos c
            INNER JOIN usuarios u
                ON u.id = c.contacto_id
            WHERE c.usuario_id = ?
            ORDER BY
                COALESCE(c.nombre_personalizado, u.nombre),
                u.apellido
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuarioId]);

        print json_encode([
            'ok' => true,
            'contactos' => $stmt->fetchAll()
        ], JSON_UNESCAPED_UNICODE);
    }

    public function eliminarContacto($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $contactoId = isset($p['contacto_id']) ? (int)$p['contacto_id'] : 0;

        if (!$token || !$contactoId) {
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
            DELETE FROM contactos
            WHERE usuario_id = ?
            AND contacto_id = ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $usuarioId,
            $contactoId
        ]);

        print json_encode([
            'ok' => true
        ], JSON_UNESCAPED_UNICODE);
    }
}
