<?php header("Access-Control-Allow-Origin: http://localhost:8100");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
include_once("../config/database.php");
require_once __DIR__ . '/../config/tareas_programadas.php';
ejecutarTareasProgramadasEnPeticion($pdo);
include_once("../clases/llamadas.class.php");
switch ($_GET['task']) {
    case "iniciarLlamada":
        $reg = new llamadas($pdo);
        $reg->iniciarLlamada($_POST, $_GET);
        break;
    case "obtenerLlamadaEntrante":
        $reg = new llamadas($pdo);
        $reg->obtenerLlamadaEntrante($_POST, $_GET);
        break;
    case "aceptarLlamada":
        $reg = new llamadas($pdo);
        $reg->aceptarLlamada($_POST, $_GET);
        break;
    case "rechazarLlamada":
        $reg = new llamadas($pdo);
        $reg->rechazarLlamada($_POST, $_GET);
        break;
    case "cancelarLlamada":
        $reg = new llamadas($pdo);
        $reg->cancelarLlamada($_POST, $_GET);
        break;
    case "finalizarLlamada":
        $reg = new llamadas($pdo);
        $reg->finalizarLlamada($_POST, $_GET);
        break;
    case "guardarOferta":
        $reg = new llamadas($pdo);
        $reg->guardarOferta($_POST, $_GET);
        break;

    case "obtenerOferta":
        $reg = new llamadas($pdo);
        $reg->obtenerOferta($_POST, $_GET);
        break;

    case "guardarRespuesta":
        $reg = new llamadas($pdo);
        $reg->guardarRespuesta($_POST, $_GET);
        break;

    case "obtenerRespuesta":
        $reg = new llamadas($pdo);
        $reg->obtenerRespuesta($_POST, $_GET);
        break;

    case "guardarIceCandidate":
        $reg = new llamadas($pdo);
        $reg->guardarIceCandidate($_POST, $_GET);
        break;

    case "obtenerIceCandidates":
        $reg = new llamadas($pdo);
        $reg->obtenerIceCandidates($_POST, $_GET);
        break;
    case "obtenerEstadoLlamada":
        $reg = new llamadas($pdo);
        $reg->obtenerEstadoLlamada($_POST, $_GET);
        break;


    default:
        print json_encode(['ok' => false, 'error' => 'Task no válido'], JSON_UNESCAPED_UNICODE);
        break;
}
