<?php
require_once __DIR__ . '/../../includes/app.php';

try {
    $owner = app_current_user();
    if (!$owner) {
        app_json_response([]);
    }
    app_json_response(app_fetch_plants($owner));
} catch (Throwable $e) {
    app_json_response(['error' => $e->getMessage()], 500);
}
