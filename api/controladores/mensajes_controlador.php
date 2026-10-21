<?php
require_once __DIR__ . '/../cors.php';
// C:\xampp\htdocs\new app\api\controladores\mensajes_controlador.php

include_once("../config/database.php");
require_once __DIR__ . '/../config/tareas_programadas.php';
ejecutarTareasProgramadasEnPeticion($pdo);
include_once("../clases/mensajes.class.php");

switch ($_GET['task'])
{
    case "enviarMensaje":

        $reg = new mensajes($pdo);
        $reg->enviarMensaje($_POST, $_GET);

        break;

    case "enviarArchivo":
        $reg = new mensajes($pdo);
        $reg->enviarArchivo($_POST, $_GET);
        break;

    case "eliminarMensajeParaTodos":
        $reg = new mensajes($pdo);
        $reg->eliminarMensajeParaTodos($_POST, $_GET);
        break;

    case "obtenerArchivo":
        $reg = new mensajes($pdo);
        $reg->obtenerArchivoAdjunto($_POST, $_GET);
        break;

    case "obtenerMensajes":

        $reg = new mensajes($pdo);
        $reg->obtenerMensajes($_POST, $_GET);

        break;

    default:

        print json_encode([
            'ok' => false,
            'error' => 'Task no vÃ¡lido'
        ], JSON_UNESCAPED_UNICODE);

        break;
}

