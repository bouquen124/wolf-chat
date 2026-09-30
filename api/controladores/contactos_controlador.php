<?php

header("Access-Control-Allow-Origin: http://localhost:8100");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
{
    http_response_code(200);
    exit;
}

include_once("../config/database.php");
include_once("../clases/contactos.class.php");

switch ($_GET['task'])
{
    case "agregarContacto":

        $reg = new contactos($pdo);
        $reg->agregarContacto($_POST, $_GET);

        break;

    case "obtenerContactos":

        $reg = new contactos($pdo);
        $reg->obtenerContactos($_POST, $_GET);

        break;

    case "eliminarContacto":

        $reg = new contactos($pdo);
        $reg->eliminarContacto($_POST, $_GET);

        break;

    default:

        print json_encode([
            'ok' => false,
            'error' => 'Task no válido'
        ], JSON_UNESCAPED_UNICODE);

        break;
}