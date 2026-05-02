<?php
require_once __DIR__ . '/../../includes/app.php';

$user = app_require_user_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['zona'])) {
    app_json_response(['success' => false, 'error' => 'Solicitud inválida'], 400);
}

try {
    $newZona = trim($_POST['zona']);
    if ($newZona === '') {
        throw new InvalidArgumentException('El nombre de la zona no puede estar vacío');
    }
    if (app_zone_exists($user, $newZona)) {
        throw new RuntimeException('Ya existe una zona con ese nombre');
    }

    $plant = app_create_plant($user, [
        'zona' => $newZona,
        'identificacion' => 'Nueva planta',
        'descripcion' => 'Descripción pendiente',
        'estado' => 'Estado pendiente',
        'riego' => '',
        'sistema_riego' => '',
        'imagenes' => [],
        'orden' => 0,
        'orden_zona' => app_next_zone_order($user),
    ]);

    app_json_response(['success' => true, 'plant' => $plant]);
} catch (Throwable $e) {
    app_json_response(['success' => false, 'error' => $e->getMessage()], 500);
}
