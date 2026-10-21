<?php
require_once __DIR__ . '/../cors.php';
include_once("../config/database.php");
require_once __DIR__ . '/../config/tareas_programadas.php';
ejecutarTareasProgramadasEnPeticion($pdo);
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
            'error' => 'Task no vÃ¡lido'
        ], JSON_UNESCAPED_UNICODE);

        break;
}

