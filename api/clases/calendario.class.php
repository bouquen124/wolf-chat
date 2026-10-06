<?php
class calendario
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function responder($datos, $codigo = 200)
    {
        http_response_code($codigo);
        print json_encode($datos, JSON_UNESCAPED_UNICODE);
    }

    private function validarSesion($token)
    {
        if (!$token) return false;

        $stmt = $this->pdo->prepare(
            "SELECT usuario_id FROM sesiones
             WHERE token = ? AND estado = 'activa'
             AND (fecha_expiracion IS NULL OR fecha_expiracion > NOW())
             LIMIT 1"
        );
        $stmt->execute([$token]);
        return $stmt->fetchColumn();
    }

    private function colorValido($color)
    {
        $colores = ['#6366f1', '#25d366', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899'];
        return in_array(strtolower($color), $colores, true) ? strtolower($color) : '#6366f1';
    }

    public function obtenerEventos($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->validarSesion($p['token'] ?? '');
        if (!$usuarioId) {
            $this->responder(['ok' => false, 'error' => 'Sesión no válida'], 401);
            return;
        }

        $anio = filter_var($p['anio'] ?? null, FILTER_VALIDATE_INT);
        $mes = filter_var($p['mes'] ?? null, FILTER_VALIDATE_INT);
        if (!$anio || !$mes || $mes < 1 || $mes > 12) {
            $this->responder(['ok' => false, 'error' => 'Mes o año no válido'], 400);
            return;
        }

        try {
            $desde = sprintf('%04d-%02d-01', $anio, $mes);
            $hasta = date('Y-m-d', strtotime($desde . ' +1 month'));
            $stmt = $this->pdo->prepare(
                "SELECT id, fecha, titulo, hora, color
                 FROM calendario_eventos
                 WHERE usuario_id = ? AND fecha >= ? AND fecha < ?
                 ORDER BY fecha ASC, hora ASC, id ASC"
            );
            $stmt->execute([$usuarioId, $desde, $hasta]);
            $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($eventos as &$evento) {
                $evento['id'] = (int)$evento['id'];
                $evento['hora'] = substr($evento['hora'], 0, 5);
            }
            unset($evento);
            $this->responder(['ok' => true, 'eventos' => $eventos]);
        } catch (PDOException $e) {
            error_log('obtenerEventos calendario: ' . $e->getMessage());
            $this->responder(['ok' => false, 'error' => 'No se pudieron cargar los eventos'], 500);
        }
    }

    public function crearEvento($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->validarSesion($p['token'] ?? '');
        if (!$usuarioId) {
            $this->responder(['ok' => false, 'error' => 'Sesión no válida'], 401);
            return;
        }

        $fecha = trim($p['fecha'] ?? '');
        $titulo = trim($p['titulo'] ?? '');
        $hora = trim($p['hora'] ?? '');
        $color = $this->colorValido(trim($p['color'] ?? '#6366f1'));

        $partesFecha = [];
        if (
            !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partesFecha)
            || !checkdate((int)$partesFecha[2], (int)$partesFecha[3], (int)$partesFecha[1])
        ) {
            $this->responder(['ok' => false, 'error' => 'Fecha no válida'], 400);
            return;
        }
        if ($titulo === '' || strlen($titulo) > 480) {
            $this->responder(['ok' => false, 'error' => 'Escribe un título de hasta 120 caracteres'], 400);
            return;
        }
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) {
            $this->responder(['ok' => false, 'error' => 'Hora no válida'], 400);
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO calendario_eventos (usuario_id, fecha, titulo, hora, color)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$usuarioId, $fecha, $titulo, $hora . ':00', $color]);
            $this->responder(['ok' => true, 'evento_id' => (int)$this->pdo->lastInsertId()]);
        } catch (PDOException $e) {
            error_log('crearEvento calendario: ' . $e->getMessage());
            $this->responder(['ok' => false, 'error' => 'No se pudo guardar el evento'], 500);
        }
    }

    public function eliminarEvento($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->validarSesion($p['token'] ?? '');
        if (!$usuarioId) {
            $this->responder(['ok' => false, 'error' => 'Sesión no válida'], 401);
            return;
        }

        $eventoId = filter_var($p['evento_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$eventoId || $eventoId < 1) {
            $this->responder(['ok' => false, 'error' => 'Evento no válido'], 400);
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM calendario_eventos WHERE id = ? AND usuario_id = ?"
            );
            $stmt->execute([$eventoId, $usuarioId]);
            if ($stmt->rowCount() === 0) {
                $this->responder(['ok' => false, 'error' => 'No se encontró el evento'], 404);
                return;
            }
            $this->responder(['ok' => true, 'mensaje' => 'Evento eliminado']);
        } catch (PDOException $e) {
            error_log('eliminarEvento calendario: ' . $e->getMessage());
            $this->responder(['ok' => false, 'error' => 'No se pudo eliminar el evento'], 500);
        }
    }
}
