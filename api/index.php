<?php if (!isset($_GET['controlador'])) {
    echo json_encode(['ok' => false, 'error' => 'Controlador no especificado'], JSON_UNESCAPED_UNICODE);
    exit;
}
switch ($_GET['controlador']) {
    case "usuarios":
        include_once("controladores/usuarios_controlador.php");
        break;
    default:
        echo json_encode(['ok' => false, 'error' => 'Controlador no válido'], JSON_UNESCAPED_UNICODE);
        break;
}
