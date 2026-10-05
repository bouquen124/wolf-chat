<?php

class notificaciones
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    private function validarSesion($token)
    {
        $stmt = $this->pdo->prepare(
            "SELECT usuario_id FROM sesiones WHERE token = ? AND estado = 'activa' LIMIT 1"
        );
        $stmt->execute([$token]);
        return $stmt->fetchColumn();
    }

    private function puedeVerFotoPerfil($propietarioId, $visualizadorId)
    {
        if (!$propietarioId || (int)$propietarioId === (int)$visualizadorId) return true;

        $stmt = $this->pdo->prepare(
            "SELECT foto_perfil FROM privacidad_usuario WHERE usuario_id = ? LIMIT 1"
        );
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

    private function urlRuta($ruta)
    {
        if (empty($ruta)) return '';
        if (strpos($ruta, 'http://') === 0 || strpos($ruta, 'https://') === 0) return $ruta;
        return 'http://localhost' . $ruta;
    }

    public function obtenerPreferencias($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->validarSesion($p['token'] ?? '');
        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $stmt = $this->pdo->prepare(
            "SELECT privados, grupos, estados FROM preferencias_notificaciones WHERE usuario_id = ? LIMIT 1"
        );
        $stmt->execute([$usuarioId]);
        $preferencias = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'privados' => 1,
            'grupos' => 1,
            'estados' => 1
        ];
        foreach ($preferencias as $campo => $valor) {
            $preferencias[$campo] = (bool)$valor;
        }

        print json_encode(['ok' => true, 'preferencias' => $preferencias], JSON_UNESCAPED_UNICODE);
    }

    public function guardarPreferencias($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->validarSesion($p['token'] ?? '');
        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $preferencias = [];
        foreach (['privados', 'grupos', 'estados'] as $campo) {
            $valor = $p[$campo] ?? null;
            if (!in_array((string)$valor, ['0', '1'], true)) {
                print json_encode(['ok' => false, 'error' => 'Preferencias incompletas'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $preferencias[$campo] = (int)$valor;
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO preferencias_notificaciones (usuario_id, privados, grupos, estados)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE privados = VALUES(privados), grupos = VALUES(grupos), estados = VALUES(estados)"
            );
            $stmt->execute([$usuarioId, $preferencias['privados'], $preferencias['grupos'], $preferencias['estados']]);

            print json_encode([
                'ok' => true,
                'preferencias' => [
                    'privados' => (bool)$preferencias['privados'],
                    'grupos' => (bool)$preferencias['grupos'],
                    'estados' => (bool)$preferencias['estados']
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('guardarPreferenciasNotificaciones: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudieron guardar las preferencias'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function obtenerPendiente($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->validarSesion($p['token'] ?? '');
        if (!$usuarioId) {
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                "SELECT n.id, n.tipo, n.titulo, n.contenido, n.referencia_id,
                        n.remitente_id, u.foto
                 FROM notificaciones n
                 LEFT JOIN usuarios u ON u.id = n.remitente_id
                 WHERE n.usuario_id = ? AND n.notificada = 0
                   AND (n.tipo <> 'estado' OR EXISTS (
                       SELECT 1 FROM estados e
                       WHERE e.id = n.referencia_id AND e.estado = 'activo' AND e.fecha_expiracion > NOW()
                   ))
                 ORDER BY n.id ASC
                 LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$usuarioId]);
            $notificacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($notificacion) {
                $actualizar = $this->pdo->prepare(
                    "UPDATE notificaciones SET notificada = 1 WHERE id = ? AND usuario_id = ?"
                );
                $actualizar->execute([$notificacion['id'], $usuarioId]);
            }
            $this->pdo->commit();

            if ($notificacion) {
                $notificacion['id'] = (int)$notificacion['id'];
                $notificacion['referencia_id'] = $notificacion['referencia_id'] !== null
                    ? (int)$notificacion['referencia_id'] : null;
                $notificacion['remitente_id'] = $notificacion['remitente_id'] !== null
                    ? (int)$notificacion['remitente_id'] : null;
                if (!$this->puedeVerFotoPerfil($notificacion['remitente_id'], $usuarioId)) {
                    $notificacion['foto'] = null;
                } else {
                    $notificacion['foto'] = $this->urlRuta($notificacion['foto']);
                }
            }
            print json_encode(['ok' => true, 'notificacion' => $notificacion ?: null], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('obtenerNotificacionPendiente: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo consultar la notificación'], JSON_UNESCAPED_UNICODE);
        }
    }
}
