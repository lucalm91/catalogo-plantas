<?php
require_once __DIR__ . '/includes/app.php';

$user = app_require_user_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ordered_zones'])) {
    app_json_response(['success' => false, 'error' => 'Solicitud inválida'], 400);
}

try {
    $orderedZones = json_decode($_POST['ordered_zones'], true);
    if (!is_array($orderedZones)) {
        throw new InvalidArgumentException('Datos de orden inválidos');
    }
    app_update_zone_order($user, $orderedZones);
    app_json_response(['success' => true]);
} catch (Throwable $e) {
    app_json_response(['success' => false, 'error' => $e->getMessage()], 500);
}
