<?php
require_once __DIR__ . '/../../includes/app.php';

$user = app_require_user_json();
$num = isset($_POST['num']) ? intval($_POST['num']) : 0;

if (!$num) {
    app_json_response(['success' => false, 'error' => 'NÃºmero de planta invÃ¡lido'], 400);
}

try {
    if (!app_delete_plant($user, $num)) {
        app_json_response(['success' => false, 'error' => 'Planta no encontrada'], 404);
    }
    app_json_response(['success' => true]);
} catch (Throwable $e) {
    app_json_response(['success' => false, 'error' => $e->getMessage()], 500);
}
