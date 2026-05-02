<?php
require_once __DIR__ . '/includes/app.php';

$owner = app_current_user() ?: 'Sistema';
$user = isset($_SESSION['user']) ? (string) $_SESSION['user'] : 'Sistema';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['plant_num'], $_POST['accion'])) {
    app_json_response(['error' => 'Solicitud inválida.'], 400);
}

try {
    $plantNum = intval($_POST['plant_num']);
    $accion = (string) $_POST['accion'];
    $oldValue = isset($_POST['old_value']) ? (string) $_POST['old_value'] : null;
    $newValue = isset($_POST['new_value']) ? (string) $_POST['new_value'] : null;

    foreach (['oldValue', 'newValue'] as $key) {
        if ($$key === '' || $$key === 'null' || $$key === 'undefined') {
            $$key = null;
        }
    }

    if ($oldValue !== null && $newValue !== null) {
        $detalles = "De: '" . app_summary($oldValue) . "' -> '" . app_summary($newValue) . "'";
    } else {
        $detalles = isset($_POST['detalles']) ? app_summary((string) $_POST['detalles']) : '';
    }

    app_add_history($owner, $plantNum, $user, app_format_action_name($accion), $detalles, $oldValue, $newValue);
    app_json_response(['success' => 'Cambio registrado.']);
} catch (Throwable $e) {
    app_json_response(['error' => $e->getMessage()], 500);
}
