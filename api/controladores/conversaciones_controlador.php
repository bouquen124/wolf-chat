<?php

header("Access-Control-Allow-Origin: http://localhost:8100");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once("../config/database.php");
include_once("../clases/conversaciones.class.php");

switch ($_GET['task']) {
    case "crearConversacion":

        $reg = new conversaciones($pdo);
        $reg->crearConversacion($_POST, $_GET);

        break;

    case "obtenerConversacionPrivada":

        $reg = new conversaciones($pdo);
        $reg->obtenerConversacionPrivada($_POST, $_GET);

        break;

    case "obtenerContacto":

        $reg = new conversaciones($pdo);
        $reg->obtenerContacto($_POST, $_GET);

        break;

    case "obtenerChats":

        $reg = new conversaciones($pdo);
        $reg->obtenerChats($_POST, $_GET);

        break;

    case "crearGrupo":

        $reg = new conversaciones($pdo);
        $reg->crearGrupo($_POST, $_GET);

        break;

    case "obtenerGrupos":
        $reg = new conversaciones($pdo);
        $reg->obtenerGrupos($_POST, $_GET);
        break;

    default:

        print json_encode([
            'ok' => false,
            'error' => 'Task no válido'
        ], JSON_UNESCAPED_UNICODE);

        break;
}
