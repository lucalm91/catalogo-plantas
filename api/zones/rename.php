<?php
require_once __DIR__ . '/../../includes/app.php';

$user = app_require_user_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['old_zona'], $_POST['new_zona'])) {
    app_json_response(['success' => false, 'error' => 'Solicitud invÃ¡lida'], 400);
}

try {
    $oldZona = trim($_POST['old_zona']);
    $newZona = trim($_POST['new_zona']);
    if ($newZona === '') {
        throw new InvalidArgumentException('El nombre de la zona no puede estar vacÃ­o');
    }
    if (!app_rename_zone($user, $oldZona, $newZona)) {
        app_json_response(['success' => false, 'error' => 'No se encontraron plantas en la zona especificada'], 404);
    }
    app_json_response(['success' => true]);
} catch (Throwable $e) {
    app_json_response(['success' => false, 'error' => $e->getMessage()], 500);
}
