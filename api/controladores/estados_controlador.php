<?php
require_once __DIR__ . '/../cors.php';
include_once("../config/database.php");
require_once __DIR__ . '/../config/tareas_programadas.php';
ejecutarTareasProgramadasEnPeticion($pdo);
include_once("../clases/estados.class.php");

$task = $_GET['task'] ?? '';

$reg = new estados($pdo);

switch ($task) {
    case "crearEstado":
        $reg->crearEstado($_POST, $_GET);
        break;

    case "obtenerProgramados":
        $reg->obtenerEstadosProgramados($_POST, $_GET);
        break;

    case "cancelarProgramado":
        $reg->cancelarEstadoProgramado($_POST, $_GET);
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
            'error' => 'Task no vÃ¡lido'
        ], JSON_UNESCAPED_UNICODE);

        break;
}

