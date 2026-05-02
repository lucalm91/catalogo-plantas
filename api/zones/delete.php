<?php
require_once __DIR__ . '/../../includes/app.php';

$user = app_require_user_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['zona'])) {
    app_json_response(['success' => false, 'error' => 'Solicitud inválida'], 400);
}

try {
    $zona = trim($_POST['zona']);
    if ($zona === '') {
        throw new InvalidArgumentException('El nombre de la zona no puede estar vacío');
    }
    app_delete_zone($user, $zona);
    app_json_response(['success' => true]);
} catch (Throwable $e) {
    app_json_response(['success' => false, 'error' => $e->getMessage()], 500);
}
