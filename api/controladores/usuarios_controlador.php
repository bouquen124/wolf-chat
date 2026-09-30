<?php header("Access-Control-Allow-Origin: http://localhost:8100");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
switch ($_GET['task']) {
    case "getUsuarios":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->getUsuarios($_POST, $_GET);
        break;
    case "getUsuario":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->getUsuario($_POST, $_GET);
        break;
    case "insertUsuario":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->insertUsuario($_POST, $_GET);
        break;
    case "login":
        include_once("../clases/usuarios.class.php");
        $reg = new usuarios();
        $reg->login($_POST, $_GET);
        break;
}
