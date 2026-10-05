<?php
class usuarios
{
    private $conn;

    function __construct()
    {
        require_once __DIR__ . '/../config/database.php';
        $this->conn = $pdo;
    }

    function __destruct()
    {
        $this->conn = null;
    }

    private function accesoDesarrolloLocal()
    {
        $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        return in_array($origen, ['http://localhost:8100', 'http://127.0.0.1:8100'], true)
            && in_array($ip, ['127.0.0.1', '::1'], true);
    }

    function getUsuarios($p, $g)
    {
        try {
            $stmt = $this->conn->query("SELECT id, telefono, nombre, apellido, foto FROM usuarios");
            $usuarios = $stmt->fetchAll();
            print json_encode(['ok' => true, 'usuarios' => $usuarios], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'Error al consultar los usuarios'], JSON_UNESCAPED_UNICODE);
        }
    }

    function getUsuario($p, $g)
    {
        try {
            $stmt = $this->conn->prepare("SELECT id, telefono, nombre, apellido, foto FROM usuarios WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $g['id'] ?? 0]);
            $usuario = $stmt->fetch();
            print json_encode(['ok' => true, 'usuario' => $usuario], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'Error al consultar el usuario'], JSON_UNESCAPED_UNICODE);
        }
    }

    function insertUsuario($p, $g)
    {
        try {
            $stmt = $this->conn->prepare("INSERT INTO usuarios (telefono, nombre, apellido, password, foto) VALUES (:telefono, :nombre, :apellido, :password, :foto)");
            $password = password_hash($p['password'] ?? '', PASSWORD_DEFAULT);
            $stmt->execute([
                ':telefono' => $p['telefono'] ?? '',
                ':nombre' => $p['nombre'] ?? '',
                ':apellido' => $p['apellido'] ?? '',
                ':password' => $password,
                ':foto' => !empty($p['foto']) ? $p['foto'] : null,
            ]);
            print json_encode(['ok' => true, 'id' => $this->conn->lastInsertId()], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'Error al registrar el usuario'], JSON_UNESCAPED_UNICODE);
        }
    }

    function login($p, $g)
    {
        try {
            $stmt = $this->conn->prepare("SELECT id, telefono, nombre, apellido, password, foto, descripcion FROM usuarios WHERE telefono = :telefono LIMIT 1");
            $stmt->execute([':telefono' => $p['telefono'] ?? '']);
            $usuario = $stmt->fetch();

            if (!$usuario || !password_verify($p['password'] ?? '', $usuario['password'])) {
                print json_encode(['ok' => false, 'error' => 'Teléfono o contraseña incorrectos'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $token = bin2hex(random_bytes(32));
            $stmt = $this->conn->prepare("INSERT INTO sesiones (usuario_id, token) VALUES (:usuario_id, :token)");
            $stmt->execute([':usuario_id' => $usuario['id'], ':token' => $token]);
            unset($usuario['password']);

            print json_encode(['ok' => true, 'usuario' => $usuario, 'token' => $token], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'Error al iniciar sesión'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function usuarioDeSesion($token)
    {
        if (!$token) return false;

        $stmt = $this->conn->prepare(
            "SELECT usuario_id FROM sesiones WHERE token = :token AND estado = 'activa' LIMIT 1"
        );
        $stmt->execute([':token' => $token]);
        return $stmt->fetchColumn();
    }

    function actualizarPerfil($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->usuarioDeSesion($p['token'] ?? '');
        if (!$usuarioId) {
            http_response_code(401);
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $nombre = trim($p['nombre'] ?? '');
        $apellido = trim($p['apellido'] ?? '');
        $descripcion = trim($p['descripcion'] ?? '');
        if ($nombre === '' || mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100 || mb_strlen($descripcion) > 255) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'Revisa el nombre, apellido y descripción'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $this->conn->prepare(
                "UPDATE usuarios SET nombre = :nombre, apellido = :apellido, descripcion = :descripcion WHERE id = :id"
            );
            $stmt->execute([
                ':nombre' => $nombre,
                ':apellido' => $apellido !== '' ? $apellido : null,
                ':descripcion' => $descripcion !== '' ? $descripcion : null,
                ':id' => $usuarioId,
            ]);

            $stmt = $this->conn->prepare(
                "SELECT id, telefono, nombre, apellido, foto, descripcion FROM usuarios WHERE id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $usuarioId]);
            print json_encode(['ok' => true, 'usuario' => $stmt->fetch()], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('actualizarPerfil: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo actualizar el perfil'], JSON_UNESCAPED_UNICODE);
        }
    }

    function actualizarFotoPerfil($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->usuarioDeSesion($p['token'] ?? '');
        if (!$usuarioId) {
            http_response_code(401);
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'Selecciona una imagen válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $archivo = $_FILES['foto'];
        if ($archivo['size'] <= 0 || $archivo['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'La imagen debe pesar menos de 5 MB'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);
        $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($tipos[$mime])) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'Usa una imagen JPG, PNG o WebP'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $carpeta = dirname(__DIR__) . '/uploads/perfiles/';
        if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true)) {
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo preparar la carpeta de fotos'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $tipos[$mime];
            $rutaFisica = $carpeta . $nombreArchivo;
            if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) {
                throw new RuntimeException('No se pudo guardar el archivo');
            }

            $esHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $protocolo = $esHttps ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $foto = $protocolo . '://' . $host . '/new%20app/api/uploads/perfiles/' . $nombreArchivo;

            $stmt = $this->conn->prepare("UPDATE usuarios SET foto = :foto WHERE id = :id");
            $stmt->execute([':foto' => $foto, ':id' => $usuarioId]);
            print json_encode(['ok' => true, 'foto' => $foto], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if (isset($rutaFisica) && is_file($rutaFisica)) unlink($rutaFisica);
            error_log('actualizarFotoPerfil: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo actualizar la foto'], JSON_UNESCAPED_UNICODE);
        }
    }

    function cerrarSesion($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $token = $p['token'] ?? '';
        if (!$token) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'Token requerido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $this->conn->prepare(
                "UPDATE sesiones SET estado = 'cerrada' WHERE token = :token AND estado = 'activa'"
            );
            $stmt->execute([':token' => $token]);
            print json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('cerrarSesion: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo cerrar la sesión'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function leerPreferenciasUsuario($usuarioId)
    {
        $stmt = $this->conn->prepare(
            "SELECT tema_id, fondo_chat_ruta FROM preferencias_usuario WHERE usuario_id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $usuarioId]);
        $preferencias = $stmt->fetch();

        return [
            'themeId' => $preferencias['tema_id'] ?? 'verde',
            'wallpaperUrl' => $preferencias['fondo_chat_ruta'] ?? null,
            'configurada' => (bool)$preferencias,
        ];
    }

    function obtenerPreferencias($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->usuarioDeSesion($p['token'] ?? '');
        if (!$usuarioId) {
            http_response_code(401);
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $preferencias = $this->leerPreferenciasUsuario($usuarioId);
            print json_encode(['ok' => true, 'preferencias' => $preferencias, 'configurada' => $preferencias['configurada']], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('obtenerPreferencias: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudieron cargar tus preferencias'], JSON_UNESCAPED_UNICODE);
        }
    }

    function guardarTema($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->usuarioDeSesion($p['token'] ?? '');
        if (!$usuarioId) {
            http_response_code(401);
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $tema = $p['theme_id'] ?? '';
        $temasPermitidos = ['verde', 'azul', 'morado', 'naranja', 'rosa'];
        if (!in_array($tema, $temasPermitidos, true)) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'El tema seleccionado no es válido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO preferencias_usuario (usuario_id, tema_id) VALUES (:id, :tema)
                 ON DUPLICATE KEY UPDATE tema_id = VALUES(tema_id)"
            );
            $stmt->execute([':id' => $usuarioId, ':tema' => $tema]);
            print json_encode(['ok' => true, 'preferencias' => $this->leerPreferenciasUsuario($usuarioId)], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('guardarTema: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo guardar el tema'], JSON_UNESCAPED_UNICODE);
        }
    }

    function actualizarFondoChat($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->usuarioDeSesion($p['token'] ?? '');
        if (!$usuarioId) {
            http_response_code(401);
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!isset($_FILES['fondo']) || $_FILES['fondo']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'Selecciona una imagen válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $archivo = $_FILES['fondo'];
        if ($archivo['size'] <= 0 || $archivo['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'La imagen debe pesar menos de 5 MB'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);
        $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($tipos[$mime])) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'Usa una imagen JPG, PNG o WebP'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $carpeta = dirname(__DIR__) . '/uploads/fondos_chat/';
        if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true)) {
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo preparar la carpeta de fondos'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rutaFisica = null;
        try {
            $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $tipos[$mime];
            $rutaFisica = $carpeta . $nombreArchivo;
            if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) {
                throw new RuntimeException('No se pudo guardar el fondo');
            }

            $stmt = $this->conn->prepare("SELECT fondo_chat_ruta FROM preferencias_usuario WHERE usuario_id = :id LIMIT 1");
            $stmt->execute([':id' => $usuarioId]);
            $fondoAnterior = $stmt->fetchColumn();

            $rutaPublica = '/new%20app/api/uploads/fondos_chat/' . $nombreArchivo;
            $stmt = $this->conn->prepare(
                "INSERT INTO preferencias_usuario (usuario_id, fondo_chat_ruta) VALUES (:id, :ruta)
                 ON DUPLICATE KEY UPDATE fondo_chat_ruta = VALUES(fondo_chat_ruta)"
            );
            $stmt->execute([':id' => $usuarioId, ':ruta' => $rutaPublica]);

            if ($fondoAnterior && preg_match('/^[a-f0-9]{32}\.\w+$/', basename($fondoAnterior))) {
                $archivoAnterior = $carpeta . basename($fondoAnterior);
                if (is_file($archivoAnterior)) unlink($archivoAnterior);
            }

            print json_encode(['ok' => true, 'preferencias' => $this->leerPreferenciasUsuario($usuarioId)], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($rutaFisica && is_file($rutaFisica)) unlink($rutaFisica);
            error_log('actualizarFondoChat: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo guardar el fondo'], JSON_UNESCAPED_UNICODE);
        }
    }

    function eliminarFondoChat($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');
        $usuarioId = $this->usuarioDeSesion($p['token'] ?? '');
        if (!$usuarioId) {
            http_response_code(401);
            print json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $this->conn->prepare("SELECT fondo_chat_ruta FROM preferencias_usuario WHERE usuario_id = :id LIMIT 1");
            $stmt->execute([':id' => $usuarioId]);
            $fondo = $stmt->fetchColumn();
            $stmt = $this->conn->prepare("UPDATE preferencias_usuario SET fondo_chat_ruta = NULL WHERE usuario_id = :id");
            $stmt->execute([':id' => $usuarioId]);

            if ($fondo && preg_match('/^[a-f0-9]{32}\.\w+$/', basename($fondo))) {
                $archivoFisico = dirname(__DIR__) . '/uploads/fondos_chat/' . basename($fondo);
                if (is_file($archivoFisico)) unlink($archivoFisico);
            }

            print json_encode(['ok' => true, 'preferencias' => $this->leerPreferenciasUsuario($usuarioId)], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('eliminarFondoChat: ' . $e->getMessage());
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo restaurar el fondo'], JSON_UNESCAPED_UNICODE);
        }
    }

    function restablecerPasswordDesarrollo($p, $g)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->accesoDesarrolloLocal()) {
            http_response_code(403);
            print json_encode(['ok' => false, 'error' => 'Esta función solo está habilitada en desarrollo local'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $telefono = trim($p['telefono'] ?? '');
        $password = $p['password'] ?? '';

        if ($telefono === '' || $password === '') {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'Teléfono y contraseña son requeridos'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (strlen($password) < 8) {
            http_response_code(400);
            print json_encode(['ok' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("UPDATE usuarios SET password = :password WHERE telefono = :telefono LIMIT 1");
            $stmt->execute([':password' => $hash, ':telefono' => $telefono]);

            if ($stmt->rowCount() === 0) {
                $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE telefono = :telefono LIMIT 1");
                $stmt->execute([':telefono' => $telefono]);
                if (!$stmt->fetchColumn()) {
                    print json_encode(['ok' => false, 'error' => 'No se encontró una cuenta con ese teléfono'], JSON_UNESCAPED_UNICODE);
                    return;
                }
            }

            print json_encode(['ok' => true, 'mensaje' => 'Contraseña actualizada'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            http_response_code(500);
            print json_encode(['ok' => false, 'error' => 'No se pudo actualizar la contraseña'], JSON_UNESCAPED_UNICODE);
        }
    }
}
