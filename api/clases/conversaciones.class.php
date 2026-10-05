
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

    private function puedeVerFotoPerfil($propietarioId, $visualizadorId)
    {
        if ((int)$propietarioId === (int)$visualizadorId) return true;

        $stmt = $this->pdo->prepare("SELECT ocultar_foto FROM privacidad_restricciones_contacto WHERE usuario_id = ? AND contacto_id = ? LIMIT 1");
        $stmt->execute([$propietarioId, $visualizadorId]);
        if ((int)$stmt->fetchColumn() === 1) return false;

        $stmt = $this->pdo->prepare("SELECT foto_perfil FROM privacidad_usuario WHERE usuario_id = ? LIMIT 1");
        $stmt->execute([$propietarioId]);
        $visibilidad = $stmt->fetchColumn() ?: 'contactos';
        if ($visibilidad === 'nadie') return false;
        if ($visibilidad === 'todos') return true;

        $stmt = $this->pdo->prepare("SELECT 1 FROM contactos WHERE usuario_id = ? AND contacto_id = ? LIMIT 1");
        $stmt->execute([$visualizadorId, $propietarioId]);
        return (bool)$stmt->fetchColumn();
    }

    private function puedeVerDescripcion($propietarioId, $visualizadorId)
    {
        if ((int)$propietarioId === (int)$visualizadorId) return true;

        $stmt = $this->pdo->prepare("SELECT ocultar_descripcion FROM privacidad_restricciones_contacto WHERE usuario_id = ? AND contacto_id = ? LIMIT 1");
        $stmt->execute([$propietarioId, $visualizadorId]);
        if ((int)$stmt->fetchColumn() === 1) return false;

        $stmt = $this->pdo->prepare("SELECT descripcion FROM privacidad_usuario WHERE usuario_id = ? LIMIT 1");
        $stmt->execute([$propietarioId]);
        $visibilidad = $stmt->fetchColumn() ?: 'contactos';
        if ($visibilidad === 'nadie') return false;
        if ($visibilidad === 'todos') return true;

        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM contactos WHERE usuario_id = ? AND contacto_id = ? LIMIT 1"
        );
        $stmt->execute([$visualizadorId, $propietarioId]);
        return (bool)$stmt->fetchColumn();
    }

    private function obtenerPresenciaVisible($propietarioId, $visualizadorId)
    {
        if ((int)$propietarioId !== (int)$visualizadorId) {
            $stmt = $this->pdo->prepare("SELECT ocultar_ultima_conexion FROM privacidad_restricciones_contacto WHERE usuario_id = ? AND contacto_id = ? LIMIT 1");
            $stmt->execute([$propietarioId, $visualizadorId]);
            if ((int)$stmt->fetchColumn() === 1) return [null, false];

            $stmt = $this->pdo->prepare("SELECT ultima_conexion FROM privacidad_usuario WHERE usuario_id = ? LIMIT 1");
            $stmt->execute([$propietarioId]);
            $visibilidad = $stmt->fetchColumn() ?: 'contactos';
            if ($visibilidad === 'nadie') return [null, false];
            if ($visibilidad === 'contactos') {
                $stmt = $this->pdo->prepare("SELECT 1 FROM contactos WHERE usuario_id = ? AND contacto_id = ? LIMIT 1");
                $stmt->execute([$visualizadorId, $propietarioId]);
                if (!$stmt->fetchColumn()) return [null, false];
            }
        }

        $stmt = $this->pdo->prepare("SELECT ultima_actividad FROM usuario_presencia WHERE usuario_id = ? LIMIT 1");
        $stmt->execute([$propietarioId]);
        $ultimaActividad = $stmt->fetchColumn();
        return [$ultimaActividad ?: null, true];
    }

    public function registrarActividad($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->validarSesion($p['token'] ?? '');
        if (!$usuarioId) {
            http_response_code(401);
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO usuario_presencia (usuario_id, ultima_actividad) VALUES (?, NOW())
             ON DUPLICATE KEY UPDATE ultima_actividad = NOW()"
        );
        $stmt->execute([$usuarioId]);
        print json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
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
                u.foto,
                u.descripcion,
                u.telefono
            FROM conversacion_usuarios cu
            INNER JOIN usuarios u
                ON u.id = cu.usuario_id
            WHERE cu.conversacion_id = ?
            AND cu.usuario_id <> ?
            AND cu.estado = 'activo'
            AND EXISTS (
                SELECT 1
                FROM conversacion_usuarios cu_mio
                WHERE cu_mio.conversacion_id = cu.conversacion_id
                AND cu_mio.usuario_id = ?
                AND cu_mio.estado = 'activo'
            )
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $conversacionId,
            $usuarioId,
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

        if (!$this->puedeVerFotoPerfil($contacto['id'], $usuarioId)) {
            $contacto['foto'] = null;
        }
        if (!$this->puedeVerDescripcion($contacto['id'], $usuarioId)) {
            $contacto['descripcion'] = null;
        }
        [$ultimaActividad, $permitirPresencia] = $this->obtenerPresenciaVisible($contacto['id'], $usuarioId);
        $contacto['ultima_conexion'] = $permitirPresencia ? $ultimaActividad : null;
        $contacto['en_linea'] = $permitirPresencia && $ultimaActividad
            ? (strtotime($ultimaActividad) >= time() - 45) : false;

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
        CASE
            WHEN ultimo_mensaje.remitente_id = ? THEN ultimo_mensaje.estado_mensaje
            ELSE NULL
        END AS estado_ultimo_mensaje,
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
            m1.remitente_id,
            m1.mensaje,
            m1.mensaje_tipo,
            m1.fecha_envio,
            md1.estado AS estado_mensaje

        FROM mensajes m1

        LEFT JOIN mensaje_destinatarios md1
            ON md1.mensaje_id = m1.id
            AND md1.usuario_id <> m1.remitente_id

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
            $usuarioId,
            $usuarioId
        ]);

        $resultado = $stmt->fetchAll();

        foreach ($resultado as &$chat) {
            $chat['no_leidos'] = (int)($chat['no_leidos'] ?? 0);
            if (!$this->puedeVerFotoPerfil($chat['contacto_id'], $usuarioId)) {
                $chat['foto'] = null;
            }
        }

        print json_encode([
            'ok' => true,
            'chats' => $resultado
        ], JSON_UNESCAPED_UNICODE);
    }


    public function obtenerGrupos($p, $g)
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
        c.id AS conversacion_id,
        c.nombre,
        c.foto,
        ultimo_mensaje.mensaje,
        ultimo_mensaje.mensaje_tipo,
        ultimo_mensaje.fecha_envio,
        COALESCE(pendientes.no_leidos, 0) AS no_leidos

     FROM conversaciones c

     INNER JOIN conversacion_usuarios cu
        ON cu.conversacion_id = c.id
        AND cu.usuario_id = ?
        AND cu.estado = 'activo'

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

        ON ultimo_mensaje.conversacion_id = c.id

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
        AND pendientes.conversacion_id = c.id

     WHERE c.tipo = 'grupo'
     AND c.estado = 'activa'

     ORDER BY
        CASE
            WHEN ultimo_mensaje.fecha_envio IS NULL THEN 1
            ELSE 0
        END,
        ultimo_mensaje.fecha_envio DESC,
        c.nombre
     ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            $usuarioId,
            $usuarioId
        ]);

        $resultado = $stmt->fetchAll();

        foreach ($resultado as &$grupo) {
            $grupo['no_leidos'] = (int)($grupo['no_leidos'] ?? 0);
        }

        print json_encode([
            'ok' => true,
            'grupos' => $resultado
        ], JSON_UNESCAPED_UNICODE);
    }


    public function obtenerIntegrantesGrupo($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $conversacionId = isset($p['conversacion_id']) ? (int)$p['conversacion_id'] : 0;

        if (!$token || !$conversacionId) {
            print json_encode(['ok' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $usuarioId = $this->validarSesion($token);
        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Autoriza únicamente a integrantes activos del grupo solicitado.
        $sql = "
            SELECT c.id
            FROM conversaciones c
            INNER JOIN conversacion_usuarios cu
                ON cu.conversacion_id = c.id
            WHERE c.id = ?
              AND c.tipo = 'grupo'
              AND c.estado = 'activa'
              AND cu.usuario_id = ?
              AND cu.estado = 'activo'
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversacionId, $usuarioId]);

        if (!$stmt->fetchColumn()) {
            print json_encode(['ok' => false, 'error' => 'No perteneces a este grupo'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sql = "
            SELECT
                u.id,
                u.nombre,
                u.apellido,
                u.foto,
                u.descripcion,
                cu.rol
            FROM conversacion_usuarios cu
            INNER JOIN usuarios u
                ON u.id = cu.usuario_id
            WHERE cu.conversacion_id = ?
              AND cu.estado = 'activo'
              AND u.estado = 'activo'
            ORDER BY
                CASE WHEN cu.rol = 'administrador' THEN 0 ELSE 1 END,
                u.nombre,
                u.apellido
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversacionId]);
        $integrantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($integrantes as &$integrante) {
            $integrante['id'] = (int)$integrante['id'];
            if (!$this->puedeVerFotoPerfil($integrante['id'], $usuarioId)) {
                $integrante['foto'] = null;
            }
            if (!$this->puedeVerDescripcion($integrante['id'], $usuarioId)) {
                $integrante['descripcion'] = null;
            }
            [$ultimaActividad, $permitirPresencia] = $this->obtenerPresenciaVisible($integrante['id'], $usuarioId);
            $integrante['ultima_conexion'] = $permitirPresencia ? $ultimaActividad : null;
            $integrante['en_linea'] = $permitirPresencia && $ultimaActividad
                ? (strtotime($ultimaActividad) >= time() - 45) : false;
        }
        unset($integrante);

        print json_encode([
            'ok' => true,
            'integrantes' => $integrantes
        ], JSON_UNESCAPED_UNICODE);
    }

    public function salirGrupo($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $conversacionId = isset($p['conversacion_id']) ? (int)$p['conversacion_id'] : 0;
        if (!$token || !$conversacionId) {
            print json_encode(['ok' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $usuarioId = $this->validarSesion($token);
        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $sql = "
                SELECT cu.rol, u.nombre, u.apellido
                FROM conversaciones c
                INNER JOIN conversacion_usuarios cu
                    ON cu.conversacion_id = c.id
                INNER JOIN usuarios u
                    ON u.id = cu.usuario_id
                WHERE c.id = ?
                  AND c.tipo = 'grupo'
                  AND c.estado = 'activa'
                  AND cu.usuario_id = ?
                  AND cu.estado = 'activo'
                LIMIT 1
                FOR UPDATE
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$conversacionId, $usuarioId]);
            $miembro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$miembro) {
                $this->pdo->rollBack();
                print json_encode(['ok' => false, 'error' => 'No perteneces a este grupo'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $administradorSiguiente = null;
            if ($miembro['rol'] === 'administrador') {
                $sql = "
                    SELECT cu.usuario_id
                    FROM conversacion_usuarios cu
                    WHERE cu.conversacion_id = ?
                      AND cu.usuario_id <> ?
                      AND cu.estado = 'activo'
                    ORDER BY cu.fecha_ingreso ASC, cu.id ASC
                    LIMIT 1
                    FOR UPDATE
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$conversacionId, $usuarioId]);
                $administradorSiguiente = $stmt->fetchColumn();
            }

            $stmt = $this->pdo->prepare(
                "UPDATE conversacion_usuarios
                 SET estado = 'salio', fecha_salida = NOW()
                 WHERE conversacion_id = ? AND usuario_id = ? AND estado = 'activo'"
            );
            $stmt->execute([$conversacionId, $usuarioId]);

            if ($administradorSiguiente) {
                $stmt = $this->pdo->prepare(
                    "UPDATE conversacion_usuarios
                     SET rol = 'administrador'
                     WHERE conversacion_id = ? AND usuario_id = ? AND estado = 'activo'"
                );
                $stmt->execute([$conversacionId, $administradorSiguiente]);
            }

            $nombre = trim(($miembro['nombre'] ?? '') . ' ' . ($miembro['apellido'] ?? ''));
            if ($nombre === '') $nombre = 'Un integrante';
            $textoSistema = $nombre . ' salió del grupo';

            $stmt = $this->pdo->prepare(
                "INSERT INTO mensajes (conversacion_id, remitente_id, mensaje, mensaje_tipo, estado)
                 VALUES (?, ?, ?, 'sistema', 'activo')"
            );
            $stmt->execute([$conversacionId, $usuarioId, $textoSistema]);
            $mensajeId = (int)$this->pdo->lastInsertId();

            // La notificación llega solo a quienes permanecen activos en el grupo.
            $stmt = $this->pdo->prepare(
                "INSERT INTO mensaje_destinatarios (mensaje_id, usuario_id, estado)
                 SELECT ?, usuario_id, 'enviado'
                 FROM conversacion_usuarios
                 WHERE conversacion_id = ? AND estado = 'activo'"
            );
            $stmt->execute([$mensajeId, $conversacionId]);

            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM conversacion_usuarios
                 WHERE conversacion_id = ? AND estado = 'activo'"
            );
            $stmt->execute([$conversacionId]);
            if ((int)$stmt->fetchColumn() === 0) {
                $stmt = $this->pdo->prepare(
                    "UPDATE conversaciones SET estado = 'eliminada' WHERE id = ? AND tipo = 'grupo'"
                );
                $stmt->execute([$conversacionId]);
            }

            $stmt = $this->pdo->prepare(
                "UPDATE conversaciones SET fecha_actualizacion = NOW() WHERE id = ?"
            );
            $stmt->execute([$conversacionId]);

            $this->pdo->commit();
            print json_encode(['ok' => true, 'mensaje' => 'Saliste del grupo'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('salirGrupo: ' . $e->getMessage());
            print json_encode(['ok' => false, 'error' => 'No se pudo salir del grupo'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function crearGrupo($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $nombre = trim($p['nombre'] ?? '');
        $usuarios = $p['usuarios'] ?? [];

        if (!$token || !$nombre || !$usuarios) {
            print json_encode([
                'ok' => false,
                'error' => 'Datos incompletos'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        if (!is_array($usuarios)) {
            $usuarios = json_decode($usuarios, true);
        }

        if (!is_array($usuarios)) {
            print json_encode([
                'ok' => false,
                'error' => 'Integrantes no válidos'
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

        $integrantes = [];

        foreach ($usuarios as $id) {
            $id = (int)$id;

            if ($id > 0 && $id !== (int)$usuarioId && !in_array($id, $integrantes, true)) {
                $integrantes[] = $id;
            }
        }

        if (!$integrantes) {
            print json_encode([
                'ok' => false,
                'error' => 'Debes seleccionar al menos una persona integrante'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $placeholders = implode(',', array_fill(0, count($integrantes), '?'));

        $sql = "
     SELECT id
     FROM usuarios
     WHERE id IN ($placeholders)
     AND estado = 'activo'  ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($integrantes);

        $usuariosValidos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($usuariosValidos) !== count($integrantes)) {
            print json_encode([
                'ok' => false,
                'error' => 'Una o más personas seleccionadas no están disponibles'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        try {

            $this->pdo->beginTransaction();

            $sql = "
        INSERT INTO conversaciones
        (
            tipo,
            nombre,
            creador_id,
            estado
        )
        VALUES
        (
            'grupo',
            ?,
            ?,
            'activa'
        )
     ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $nombre,
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
            'administrador',
            'activo'
        )
     ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $conversacionId,
                $usuarioId
            ]);

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
        )
     ";

            $stmt = $this->pdo->prepare($sql);

            foreach ($integrantes as $integranteId) {
                $stmt->execute([
                    $conversacionId,
                    $integranteId
                ]);
            }

            $this->pdo->commit();

            print json_encode([
                'ok' => true,
                'conversacion_id' => $conversacionId
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            print json_encode([
                'ok' => false,
                'error' => 'No se pudo crear el grupo'
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
