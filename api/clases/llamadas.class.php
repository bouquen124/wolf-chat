<?php

class llamadas
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
        $stmt = $this->pdo->prepare("SELECT foto_perfil FROM privacidad_usuario WHERE usuario_id = ? LIMIT 1");
        $stmt->execute([$propietarioId]);
        $visibilidad = $stmt->fetchColumn() ?: 'contactos';
        if ($visibilidad === 'nadie') return false;
        if ($visibilidad === 'todos') return true;
        $stmt = $this->pdo->prepare("SELECT 1 FROM contactos WHERE usuario_id = ? AND contacto_id = ? LIMIT 1");
        $stmt->execute([$visualizadorId, $propietarioId]);
        return (bool)$stmt->fetchColumn();
    }

    public function iniciarLlamada($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $conversacionId = isset($p['conversacion_id']) ? (int)$p['conversacion_id'] : 0;
        $receptorId = isset($p['receptor_id']) ? (int)$p['receptor_id'] : 0;

        if (!$token || !$conversacionId || !$receptorId) {
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

        if ((int)$usuarioId === $receptorId) {
            print json_encode([
                'ok' => false,
                'error' => 'No puedes llamarte a ti mismo'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
            SELECT id
            FROM conversaciones
            WHERE id = ?
            AND tipo = 'privada'
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
            $receptorId
        ]);

        if (!$stmt->fetchColumn()) {
            print json_encode([
                'ok' => false,
                'error' => 'El receptor no pertenece a esta conversación'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
            SELECT id
            FROM llamadas
            WHERE conversacion_id = ?
            AND estado = 'llamando'
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $conversacionId
        ]);

        if ($stmt->fetchColumn()) {
            print json_encode([
                'ok' => false,
                'error' => 'Ya existe una llamada en curso'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
            INSERT INTO llamadas
            (
                conversacion_id,
                llamante_id,
                receptor_id,
                tipo,
                estado
            )
            VALUES
            (
                ?,
                ?,
                ?,
                'voz',
                'llamando'
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $conversacionId,
            $usuarioId,
            $receptorId
        ]);

        $llamadaId = $this->pdo->lastInsertId();

        print json_encode([
            'ok' => true,
            'llamada_id' => $llamadaId,
            'estado' => 'llamando'
        ], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerLlamadaEntrante($p, $g)
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
                l.id,
                l.conversacion_id,
                l.llamante_id,
                l.receptor_id,
                l.tipo,
                l.estado,
                l.fecha_inicio,
                u.nombre,
                u.apellido,
                u.foto
            FROM llamadas l
            INNER JOIN usuarios u
                ON u.id = l.llamante_id
            WHERE l.receptor_id = ?
            AND l.estado = 'llamando'
            ORDER BY l.id DESC
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $usuarioId
        ]);

        $llamada = $stmt->fetch();

        if ($llamada && !$this->puedeVerFotoPerfil($llamada['llamante_id'], $usuarioId)) {
            $llamada['foto'] = null;
        }

        print json_encode([
            'ok' => true,
            'llamada' => $llamada ?: null
        ], JSON_UNESCAPED_UNICODE);
    }

    public function aceptarLlamada($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $llamadaId = isset($p['llamada_id']) ? (int)$p['llamada_id'] : 0;

        if (!$token || !$llamadaId) {
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
            FROM llamadas
            WHERE id = ?
            AND receptor_id = ?
            AND estado = 'llamando'
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $llamadaId,
            $usuarioId
        ]);

        if (!$stmt->fetchColumn()) {
            print json_encode([
                'ok' => false,
                'error' => 'La llamada no está disponible'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
            UPDATE llamadas
            SET
                estado = 'aceptada',
                fecha_respuesta = NOW()
            WHERE id = ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $llamadaId
        ]);

        print json_encode([
            'ok' => true,
            'llamada_id' => $llamadaId,
            'estado' => 'aceptada'
        ], JSON_UNESCAPED_UNICODE);
    }

    public function rechazarLlamada($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $llamadaId = isset($p['llamada_id']) ? (int)$p['llamada_id'] : 0;

        if (!$token || !$llamadaId) {
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
            UPDATE llamadas
            SET
                estado = 'rechazada',
                fecha_fin = NOW()
            WHERE id = ?
            AND receptor_id = ?
            AND estado = 'llamando'
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $llamadaId,
            $usuarioId
        ]);

        if ($stmt->rowCount() === 0) {
            print json_encode([
                'ok' => false,
                'error' => 'La llamada no está disponible'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        print json_encode([
            'ok' => true,
            'llamada_id' => $llamadaId,
            'estado' => 'rechazada'
        ], JSON_UNESCAPED_UNICODE);
    }

    public function cancelarLlamada($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $llamadaId = isset($p['llamada_id']) ? (int)$p['llamada_id'] : 0;

        if (!$token || !$llamadaId) {
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
            UPDATE llamadas
            SET
                estado = 'cancelada',
                fecha_fin = NOW()
            WHERE id = ?
            AND llamante_id = ?
            AND estado = 'llamando'
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $llamadaId,
            $usuarioId
        ]);

        if ($stmt->rowCount() === 0) {
            print json_encode([
                'ok' => false,
                'error' => 'La llamada no está disponible'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        print json_encode([
            'ok' => true,
            'llamada_id' => $llamadaId,
            'estado' => 'cancelada'
        ], JSON_UNESCAPED_UNICODE);
    }

    public function finalizarLlamada($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $llamadaId = isset($p['llamada_id']) ? (int)$p['llamada_id'] : 0;

        if (!$token || !$llamadaId) {
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
            UPDATE llamadas
            SET
                estado = 'finalizada',
                fecha_fin = NOW()
            WHERE id = ?
            AND (llamante_id = ? OR receptor_id = ?)
            AND estado = 'aceptada'
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $llamadaId,
            $usuarioId,
            $usuarioId
        ]);

        if ($stmt->rowCount() === 0) {
            print json_encode([
                'ok' => false,
                'error' => 'La llamada no está activa'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        print json_encode([
            'ok' => true,
            'llamada_id' => $llamadaId,
            'estado' => 'finalizada'
        ], JSON_UNESCAPED_UNICODE);
    }
    public function guardarOferta($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $llamadaId = isset($p['llamada_id']) ? (int)$p['llamada_id'] : 0;
        $offerSdp = $p['offer_sdp'] ?? '';

        if (!$token || !$llamadaId || !$offerSdp) {
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
    UPDATE llamadas
    SET offer_sdp = ?
    WHERE id = ?
    AND llamante_id = ?
    AND estado = 'llamando'
";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $offerSdp,
            $llamadaId,
            $usuarioId
        ]);

        if ($stmt->rowCount() === 0) {
            print json_encode([
                'ok' => false,
                'error' => 'La llamada no está disponible'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        print json_encode([
            'ok' => true,
            'llamada_id' => $llamadaId
        ], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerOferta($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $llamadaId = isset($p['llamada_id']) ? (int)$p['llamada_id'] : 0;

        if (!$token || !$llamadaId) {
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
    SELECT offer_sdp
    FROM llamadas
    WHERE id = ?
    AND receptor_id = ?
    LIMIT 1
";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $llamadaId,
            $usuarioId
        ]);

        $offer = $stmt->fetchColumn();

        print json_encode([
            'ok' => true,
            'offer_sdp' => $offer ?: null
        ], JSON_UNESCAPED_UNICODE);
    }

    public function guardarRespuesta($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $llamadaId = isset($p['llamada_id']) ? (int)$p['llamada_id'] : 0;
        $answerSdp = $p['answer_sdp'] ?? '';

        if (!$token || !$llamadaId || !$answerSdp) {
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
    UPDATE llamadas
    SET answer_sdp = ?
    WHERE id = ?
    AND receptor_id = ?
    AND estado = 'aceptada'
";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $answerSdp,
            $llamadaId,
            $usuarioId
        ]);

        if ($stmt->rowCount() === 0) {
            print json_encode([
                'ok' => false,
                'error' => 'La llamada no está disponible'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        print json_encode([
            'ok' => true,
            'llamada_id' => $llamadaId
        ], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerRespuesta($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $llamadaId = isset($p['llamada_id']) ? (int)$p['llamada_id'] : 0;

        if (!$token || !$llamadaId) {
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
    SELECT answer_sdp
    FROM llamadas
    WHERE id = ?
    AND llamante_id = ?
    LIMIT 1
";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $llamadaId,
            $usuarioId
        ]);

        $answer = $stmt->fetchColumn();

        print json_encode([
            'ok' => true,
            'answer_sdp' => $answer ?: null
        ], JSON_UNESCAPED_UNICODE);
    }

    public function guardarIceCandidate($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $llamadaId = isset($p['llamada_id']) ? (int)$p['llamada_id'] : 0;
        $candidate = $p['candidate'] ?? '';
        $sdpMid = $p['sdp_mid'] ?? null;
        $sdpMLineIndex = isset($p['sdp_m_line_index']) && $p['sdp_m_line_index'] !== ''
            ? (int)$p['sdp_m_line_index']
            : null;

        if (!$token || !$llamadaId || !$candidate) {
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
    FROM llamadas
    WHERE id = ?
    AND (llamante_id = ? OR receptor_id = ?)
    AND estado IN ('llamando', 'aceptada')
    LIMIT 1
";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $llamadaId,
            $usuarioId,
            $usuarioId
        ]);

        if (!$stmt->fetchColumn()) {
            print json_encode([
                'ok' => false,
                'error' => 'La llamada no está disponible'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
    INSERT INTO llamada_ice_candidates
    (
        llamada_id,
        usuario_id,
        candidate,
        sdp_mid,
        sdp_m_line_index
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?
    )
";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $llamadaId,
            $usuarioId,
            $candidate,
            $sdpMid,
            $sdpMLineIndex
        ]);

        print json_encode([
            'ok' => true,
            'candidate_id' => $this->pdo->lastInsertId()
        ], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerIceCandidates($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = $p['token'] ?? '';
        $llamadaId = isset($p['llamada_id']) ? (int)$p['llamada_id'] : 0;
        $ultimoId = isset($p['ultimo_id']) ? (int)$p['ultimo_id'] : 0;

        if (!$token || !$llamadaId) {
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
    FROM llamadas
    WHERE id = ?
    AND (llamante_id = ? OR receptor_id = ?)
    LIMIT 1
";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $llamadaId,
            $usuarioId,
            $usuarioId
        ]);

        if (!$stmt->fetchColumn()) {
            print json_encode([
                'ok' => false,
                'error' => 'La llamada no está disponible'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $sql = "
    SELECT
        id,
        usuario_id,
        candidate,
        sdp_mid,
        sdp_m_line_index
    FROM llamada_ice_candidates
    WHERE llamada_id = ?
    AND usuario_id != ?
    AND id > ?
    ORDER BY id ASC
";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $llamadaId,
            $usuarioId,
            $ultimoId
        ]);

        $candidates = $stmt->fetchAll();

        print json_encode([
            'ok' => true,
            'candidates' => $candidates
        ], JSON_UNESCAPED_UNICODE);
    }

    public function obtenerEstadoLlamada($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');


        $token = $p['token'] ?? '';
        $llamadaId = isset($p['llamada_id']) ? (int)$p['llamada_id'] : 0;

        if (!$token || !$llamadaId) {
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
    SELECT estado 
    FROM llamadas 
    WHERE id = ? 
    AND (llamante_id = ? OR receptor_id = ?) 
    LIMIT 1 
";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $llamadaId,
            $usuarioId,
            $usuarioId
        ]);

        $estado = $stmt->fetchColumn();

        if (!$estado) {
            print json_encode([
                'ok' => false,
                'error' => 'La llamada no existe'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        print json_encode([
            'ok' => true,
            'llamada_id' => $llamadaId,
            'estado' => $estado
        ], JSON_UNESCAPED_UNICODE);
    }
}
