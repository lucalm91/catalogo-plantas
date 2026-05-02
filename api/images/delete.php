<?php
require_once __DIR__ . '/../../includes/app.php';

$user = app_require_user_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['plant_num'], $_POST['imagen'])) {
    app_json_response(['error' => 'Solicitud inválida.'], 400);
}

try {
    $plantNum = intval($_POST['plant_num']);
    $imageToDelete = (string) $_POST['imagen'];

    if (!app_remove_plant_image($user, $plantNum, $imageToDelete)) {
        app_json_response(['error' => 'Imagen o planta no encontrada.'], 404);
    }

    $absolutePath = app_root() . '/' . ltrim($imageToDelete, '/\\');
    if (file_exists($absolutePath)) {
        unlink($absolutePath);
    }

    app_json_response(['success' => 'Imagen eliminada.']);
} catch (Throwable $e) {
    app_json_response(['error' => $e->getMessage()], 500);
}
