<?php
header("Access-Control-Allow-Origin: http://localhost:8100");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$task = $_GET['task'] ?? '';

switch ($task) {
    case "getUsuarios":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->getUsuarios($_POST, $_GET);
        break;

    case "getUsuario":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->getUsuario($_POST, $_GET);
        break;

    case "insertUsuario":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->insertUsuario($_POST, $_GET);
        break;

    case "login":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->login($_POST, $_GET);
        break;

    case "actualizarPerfil":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->actualizarPerfil($_POST, $_GET);
        break;

    case "actualizarFotoPerfil":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->actualizarFotoPerfil($_POST, $_GET);
        break;

    case "cerrarSesion":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->cerrarSesion($_POST, $_GET);
        break;

    case "obtenerPreferencias":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->obtenerPreferencias($_POST, $_GET);
        break;

    case "guardarTema":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->guardarTema($_POST, $_GET);
        break;

    case "actualizarFondoChat":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->actualizarFondoChat($_POST, $_GET);
        break;

    case "eliminarFondoChat":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->eliminarFondoChat($_POST, $_GET);
        break;

    case "restablecerPasswordDesarrollo":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->restablecerPasswordDesarrollo($_POST, $_GET);
        break;

    default:
        http_response_code(400);
        print json_encode(['ok' => false, 'error' => 'Task no válido'], JSON_UNESCAPED_UNICODE);
        break;
}
