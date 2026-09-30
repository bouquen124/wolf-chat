<?php class usuarios
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




    function getUsuarios($p, $g)
    {
        try {
            $stmt = $this->conn->query("SELECT id, telefono, nombre, apellido, foto FROM usuarios");

            $usuarios = $stmt->fetchAll();

            print json_encode([
                'ok' => true,
                'usuarios' => $usuarios
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            http_response_code(500);

            print json_encode([
                'ok' => false,
                'error' => 'Error al consultar los usuarios'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    function getUsuario($p, $g)
    {
        try {
            $stmt = $this->conn->prepare("SELECT id, telefono, nombre, apellido, foto FROM usuarios");


            $stmt->execute([
                ':id' => $g['id']
            ]);

            $usuario = $stmt->fetch();

            print json_encode([
                'ok' => true,
                'usuario' => $usuario
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            http_response_code(500);

            print json_encode([
                'ok' => false,
                'error' => 'Error al consultar el usuario'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    function insertUsuario($p, $g)
    {
        try {
            $stmt = $this->conn->prepare("INSERT INTO usuarios (telefono,nombre,apellido,password,foto) VALUES (:telefono,:nombre,:apellido,:password,:foto)");

            $password = password_hash($p['password'], PASSWORD_DEFAULT);

            $stmt->execute([
                ':telefono' => $p['telefono'],
                ':nombre' => $p['nombre'],
                ':apellido' => $p['apellido'],
                ':password' => $password,
                ':foto' => !empty($p['foto']) ? $p['foto'] : null
            ]);

            print json_encode([
                'ok' => true,
                'id' => $this->conn->lastInsertId()
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            http_response_code(500);

            print json_encode([
                'ok' => false,
                'error' => 'Error al registrar el usuario'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    function login($p, $g)
    {
        try {
            $stmt = $this->conn->prepare("SELECT id,telefono,nombre,apellido,password,foto FROM usuarios WHERE telefono = :telefono");

            $stmt->execute([
                ':telefono' => $p['telefono']
            ]);

            $usuario = $stmt->fetch();

            if (!$usuario) {
                print json_encode([
                    'ok' => false,
                    'error' => 'El teléfono no está registrado'
                ], JSON_UNESCAPED_UNICODE);

                return;
            }

            if (!password_verify($p['password'], $usuario['password'])) {
                print json_encode([
                    'ok' => false,
                    'error' => 'Contraseña incorrecta'
                ], JSON_UNESCAPED_UNICODE);

                return;
            }

            $token = bin2hex(random_bytes(32));


            $stmt = $this->conn->prepare("INSERT INTO sesiones (usuario_id,token) VALUES (:usuario_id,:token)");

            $stmt->execute([
                ':usuario_id' => $usuario['id'],
                ':token' => $token
            ]);

            unset($usuario['password']);

            print json_encode([
                'ok' => true,
                'usuario' => $usuario,
                'token' => $token
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            http_response_code(500);

            print json_encode([
                'ok' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
