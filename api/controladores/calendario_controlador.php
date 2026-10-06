<?php
header('Access-Control-Allow-Origin: http://localhost:8100');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once('../config/database.php');
require_once __DIR__ . '/../config/tareas_programadas.php';
ejecutarTareasProgramadasEnPeticion($pdo);
include_once('../clases/calendario.class.php');
$reg = new calendario($pdo);
$task = $_GET['task'] ?? '';

switch ($task) {
    case 'obtenerEventos':
        $reg->obtenerEventos($_POST, $_GET);
        break;

    case 'crearEvento':
        $reg->crearEvento($_POST, $_GET);
        break;

    case 'eliminarEvento':
        $reg->eliminarEvento($_POST, $_GET);
        break;

    default:
        http_response_code(400);
        print json_encode(['ok' => false, 'error' => 'Task no válido'], JSON_UNESCAPED_UNICODE);
        break;
}
