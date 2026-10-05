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
                CASE
                    WHEN COALESCE(p.foto_perfil, 'contactos') = 'todos'
                      OR (COALESCE(p.foto_perfil, 'contactos') = 'contactos' AND EXISTS (
                          SELECT 1 FROM contactos c2 WHERE c2.usuario_id = u.id AND c2.contacto_id = c.usuario_id
                      )) THEN CASE WHEN EXISTS (
                          SELECT 1 FROM privacidad_restricciones_contacto prf WHERE prf.usuario_id = u.id AND prf.contacto_id = c.usuario_id AND prf.ocultar_foto = 1
                      ) THEN NULL ELSE u.foto END ELSE NULL
                END AS foto,
                CASE
                    WHEN COALESCE(p.descripcion, 'contactos') = 'todos'
                      OR (COALESCE(p.descripcion, 'contactos') = 'contactos' AND EXISTS (
                          SELECT 1 FROM contactos c3 WHERE c3.usuario_id = u.id AND c3.contacto_id = c.usuario_id
                      )) THEN CASE WHEN EXISTS (
                          SELECT 1 FROM privacidad_restricciones_contacto prd WHERE prd.usuario_id = u.id AND prd.contacto_id = c.usuario_id AND prd.ocultar_descripcion = 1
                      ) THEN NULL ELSE u.descripcion END ELSE NULL
                END AS descripcion
            FROM contactos c
            INNER JOIN usuarios u
                ON u.id = c.contacto_id
            LEFT JOIN privacidad_usuario p
                ON p.usuario_id = u.id
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
