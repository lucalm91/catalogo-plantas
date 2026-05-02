<?php
require_once __DIR__ . '/../../includes/app.php';

$user = app_require_user_json();

$zona = isset($_POST['zona']) ? trim($_POST['zona']) : '';
$identificacion = isset($_POST['identificacion']) ? trim($_POST['identificacion']) : '';
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
$estado = isset($_POST['estado']) ? trim($_POST['estado']) : '';
$riego = isset($_POST['riego']) ? trim($_POST['riego']) : '';
$sistema_riego = isset($_POST['sistema_riego']) ? trim($_POST['sistema_riego']) : '';

if (!$zona || !$identificacion || !$descripcion || !$estado) {
    app_json_response(['success' => false, 'error' => 'Faltan datos obligatorios'], 400);
}

try {
    $plant = app_create_plant($user, [
        'zona' => $zona,
        'identificacion' => $identificacion,
        'descripcion' => $descripcion,
        'estado' => $estado,
        'riego' => $riego,
        'sistema_riego' => $sistema_riego,
        'imagenes' => [],
    ]);
    app_json_response(['success' => true, 'num' => $plant['num'], 'plant' => $plant]);
} catch (Throwable $e) {
    app_json_response(['success' => false, 'error' => $e->getMessage()], 500);
}
