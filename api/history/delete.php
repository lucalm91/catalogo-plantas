<?php
require_once __DIR__ . '/../../includes/app.php';

$owner = app_require_user_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['plant_num'], $_POST['fecha'])) {
    app_json_response(['error' => 'Solicitud invÃ¡lida.'], 400);
}

try {
    $plantNum = intval($_POST['plant_num']);
    $fecha = (string) $_POST['fecha'];

    if (!app_delete_history_entry($owner, $plantNum, $fecha)) {
        app_json_response(['error' => 'Entrada de historial no encontrada.'], 404);
    }
    app_json_response(['success' => 'Entrada de historial eliminada.']);
} catch (Throwable $e) {
    app_json_response(['error' => $e->getMessage()], 500);
}
