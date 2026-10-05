<?php

header("Access-Control-Allow-Origin: http://localhost:8100");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once("../config/database.php");
include_once("../clases/notificaciones.class.php");

$reg = new notificaciones($pdo);
switch ($_GET['task'] ?? '') {
    case 'obtenerPreferencias':
        $reg->obtenerPreferencias($_POST, $_GET);
        break;
    case 'guardarPreferencias':
        $reg->guardarPreferencias($_POST, $_GET);
        break;
    case 'obtenerPendiente':
        $reg->obtenerPendiente($_POST, $_GET);
        break;
    default:
        http_response_code(400);
        print json_encode(['ok' => false, 'error' => 'Task no válido'], JSON_UNESCAPED_UNICODE);
        break;
}
