<?php
require_once __DIR__ . '/../../includes/app.php';

$user = app_require_user_json();

$plantNum = isset($_POST['plant_num']) ? intval($_POST['plant_num']) : 0;
$zona = isset($_POST['zona']) ? (string) $_POST['zona'] : '';
$direction = isset($_POST['direction']) ? (string) $_POST['direction'] : '';

if (!$plantNum || $zona === '' || !in_array($direction, ['up', 'down'], true)) {
    app_json_response(['success' => false, 'error' => 'Datos invÃ¡lidos'], 400);
}

try {
    $plants = array_values(array_filter(app_fetch_plants($user), fn($p) => $p['zona'] === $zona));
    usort($plants, fn($a, $b) => ((int) $a['orden']) <=> ((int) $b['orden']));
    $idx = array_search($plantNum, array_column($plants, 'num'), true);
    if ($idx === false) {
        app_json_response(['success' => false, 'error' => 'Planta no encontrada'], 404);
    }
    $swapIdx = $direction === 'up' ? $idx - 1 : $idx + 1;
    if ($swapIdx < 0 || $swapIdx >= count($plants)) {
        app_json_response(['success' => false, 'error' => 'No se puede mover mÃ¡s'], 400);
    }
    $tmp = $plants[$idx]['orden'];
    $plants[$idx]['orden'] = $plants[$swapIdx]['orden'];
    $plants[$swapIdx]['orden'] = $tmp;

    app_update_order($user, [
        ['plant_num' => $plants[$idx]['num'], 'orden' => $plants[$idx]['orden']],
        ['plant_num' => $plants[$swapIdx]['num'], 'orden' => $plants[$swapIdx]['orden']],
    ]);
    app_json_response(['success' => true]);
} catch (Throwable $e) {
    app_json_response(['success' => false, 'error' => $e->getMessage()], 500);
}
