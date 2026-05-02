<?php
require_once __DIR__ . '/includes/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['plant_num'])) {
    app_json_response(['error' => 'Solicitud inválida.'], 400);
}

try {
    $owner = app_current_user();
    if (!$owner) {
        app_json_response([]);
    }

    $history = app_fetch_history($owner, intval($_GET['plant_num']));
    foreach ($history as &$entry) {
        if (isset($entry['fecha'])) {
            $timestamp = strtotime($entry['fecha']);
            if ($timestamp) {
                $entry['fecha'] = date('d/m/Y H:i', $timestamp);
            }
        }
        if (isset($entry['old_value'], $entry['new_value'])) {
            $entry['detalle'] = "De {$entry['old_value']} a {$entry['new_value']}";
        }
    }
    app_json_response(array_values($history));
} catch (Throwable $e) {
    app_json_response(['error' => $e->getMessage()], 500);
}
