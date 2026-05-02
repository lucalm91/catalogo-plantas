<?php
require_once __DIR__ . '/../../includes/app.php';

$user = app_require_user_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['plant_num'], $_POST['field'], $_POST['value'])) {
    app_json_response(['error' => 'Solicitud inválida.'], 400);
}

try {
    $plantNum = intval($_POST['plant_num']);
    $field = (string) $_POST['field'];
    $value = (string) $_POST['value'];

    if (!app_update_plant_field($user, $plantNum, $field, $value)) {
        app_json_response(['error' => 'Planta no encontrada.'], 404);
    }

    app_json_response(['success' => 'Campo actualizado.']);
} catch (InvalidArgumentException $e) {
    app_json_response(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    app_json_response(['error' => $e->getMessage()], 500);
}
