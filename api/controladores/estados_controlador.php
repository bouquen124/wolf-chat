<?php

header("Access-Control-Allow-Origin: http://localhost:8100");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once("../config/database.php");
include_once("../clases/estados.class.php");

$task = $_GET['task'] ?? '';

$reg = new estados($pdo);

switch ($task) {
    case "crearEstado":
        $reg->crearEstado($_POST, $_GET);
        break;

    case "obtenerMisEstados":
        $reg->obtenerMisEstados($_POST, $_GET);
        break;

    case "obtenerEstados":
        $reg->obtenerEstados($_POST, $_GET);
        break;

    case "obtenerEstadosPorUsuario":
        $reg->obtenerEstadosPorUsuario($_POST, $_GET);
        break;

    case "obtenerEstado":
        $reg->obtenerEstado($_POST, $_GET);
        break;

    case "marcarVisto":
        $reg->marcarVisto($_POST, $_GET);
        break;

    case "obtenerVistas":
    case "obtenerVisores":
        $reg->obtenerVistas($_POST, $_GET);
        break;

    case "eliminarEstado":
        $reg->eliminarEstado($_POST, $_GET);
        break;

    default:
        header('Content-Type: application/json; charset=utf-8');

        print json_encode([
            'ok' => false,
            'error' => 'Task no válido'
        ], JSON_UNESCAPED_UNICODE);

        break;
}
