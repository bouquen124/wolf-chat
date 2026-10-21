<?php
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json; charset=utf-8');

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
        print json_encode(['ok' => false, 'error' => 'Task no vÃ¡lido'], JSON_UNESCAPED_UNICODE);
        break;
}

