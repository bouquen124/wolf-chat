<?php // C:\xampp\htdocs\new app\api\controladores\mensajes_controlador.php

header("Access-Control-Allow-Origin: http://localhost:8100");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
{
    http_response_code(200);
    exit;
}

include_once("../config/database.php");
include_once("../clases/mensajes.class.php");

switch ($_GET['task'])
{
    case "enviarMensaje":

        $reg = new mensajes($pdo);
        $reg->enviarMensaje($_POST, $_GET);

        break;

    case "obtenerMensajes":

        $reg = new mensajes($pdo);
        $reg->obtenerMensajes($_POST, $_GET);

        break;

    default:

        print json_encode([
            'ok' => false,
            'error' => 'Task no válido'
        ], JSON_UNESCAPED_UNICODE);

        break;
}