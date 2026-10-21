<?php
$origenesPermitidos = [
    'http://localhost:8100',
    'https://wolf-chat-nine.vercel.app'
];

$origen = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if (in_array($origen, $origenesPermitidos, true)) {
    header('Access-Control-Allow-Origin: ' . $origen);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
