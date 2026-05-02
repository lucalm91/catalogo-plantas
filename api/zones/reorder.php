<?php
require_once __DIR__ . '/../../includes/app.php';

$user = app_require_user_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['zona'], $_POST['direction'])) {
    app_json_response(['success' => false, 'error' => 'ParÃ¡metros faltantes o mÃ©todo incorrecto.'], 400);
}

try {
    $targetZone = (string) $_POST['zona'];
    $direction = (string) $_POST['direction'];
    if (!in_array($direction, ['up', 'down'], true)) {
        throw new InvalidArgumentException('DirecciÃ³n invÃ¡lida');
    }

    $zones = [];
    foreach (app_fetch_plants($user) as $plant) {
        $zone = $plant['zona'];
        if (!isset($zones[$zone])) {
            $zones[$zone] = (int) $plant['orden_zona'];
        }
    }
    asort($zones);
    $ordered = array_keys($zones);
    $idx = array_search($targetZone, $ordered, true);
    if ($idx === false) {
        app_json_response(['success' => false, 'error' => 'Zona no encontrada en la lista ordenada.'], 404);
    }
    $swapIdx = $direction === 'up' ? $idx - 1 : $idx + 1;
    if ($swapIdx < 0 || $swapIdx >= count($ordered)) {
        app_json_response(['success' => false, 'error' => 'No se puede mover mÃ¡s la zona.'], 400);
    }
    [$ordered[$idx], $ordered[$swapIdx]] = [$ordered[$swapIdx], $ordered[$idx]];
    app_update_zone_order($user, $ordered);
    app_json_response(['success' => true]);
} catch (Throwable $e) {
    app_json_response(['success' => false, 'error' => $e->getMessage()], 500);
}
