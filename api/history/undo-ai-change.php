<?php
require_once __DIR__ . '/../../includes/app.php';

$owner = app_require_user_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['plant_num'], $_POST['fecha'])) {
    app_json_response(['error' => 'Solicitud invÃ¡lida.'], 400);
}

try {
    $plantNum = intval($_POST['plant_num']);
    $fecha = (string) $_POST['fecha'];
    $date = DateTime::createFromFormat('d/m/Y H:i', $fecha);
    $fechaSql = $date ? $date->format('Y-m-d H:i:s') : $fecha;

    $stmt = app_db()->prepare(
        'SELECT * FROM plant_history WHERE owner = ? AND plant_num = ? AND fecha = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$owner, $plantNum, $fechaSql]);
    $log = $stmt->fetch();
    if (!$log || empty($log['old_value'])) {
        app_json_response(['error' => 'No se encontrÃ³ el cambio para deshacer.'], 404);
    }

    $field = match ($log['accion']) {
        'Cambio de nombre' => 'identificacion',
        'DescripciÃ³n' => 'descripcion',
        'Estado' => 'estado',
        'Riego' => 'riego',
        'Sistema de riego' => 'sistema_riego',
        default => null,
    };

    if (!$field) {
        app_json_response(['error' => 'Campo no permitido para deshacer.'], 400);
    }

    if (!app_update_plant_field($owner, $plantNum, $field, (string) $log['old_value'])) {
        app_json_response(['error' => 'No se pudo restaurar el valor anterior.'], 404);
    }

    app_add_history(
        $owner,
        $plantNum,
        (string) $_SESSION['user'],
        'Deshacer ' . $log['accion'],
        "Restaurado a: '" . app_summary((string) $log['old_value']) . "'",
        isset($log['new_value']) ? (string) $log['new_value'] : null,
        (string) $log['old_value']
    );

    app_json_response([
        'success' => true,
        'restored_field' => $field,
        'restored_value' => $log['old_value'],
    ]);
} catch (Throwable $e) {
    app_json_response(['error' => $e->getMessage()], 500);
}
