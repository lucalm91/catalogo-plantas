<?php
session_start();
header('Content-Type: application/json');

$user = isset($_SESSION['user']) ? $_SESSION['user'] : "Sistema";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["plant_num"]) && isset($_POST["accion"])) {
    $plant_num = intval($_POST["plant_num"]);
    $accion = $_POST["accion"];
    $fecha = date("Y-m-d H:i:s");
    $old_value = isset($_POST["old_value"]) ? $_POST["old_value"] : null;
    $new_value = isset($_POST["new_value"]) ? $_POST["new_value"] : null;

    // Normalize string placeholders to null
    foreach (["old_value", "new_value"] as $key) {
        if (!isset($$key)) continue;
        $val = $$key;
        if ($val === '' || $val === 'null' || $val === 'undefined') {
            $$key = null;
        }
    }

    // Detalles simplificados
    $detalles = "";
    if ($old_value !== null && $new_value !== null) {
        $detalles = "De: '" . resumen($old_value) . "' → '" . resumen($new_value) . "'";
    } elseif (isset($_POST["detalles"])) {
        $detalles = resumen($_POST["detalles"]);
    }

    $historyDir = "history/";
    if (!is_dir($historyDir)) mkdir($historyDir, 0777, true);
    $historyFile = $historyDir . "plant_history.json";
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];

    $readableAction = formatActionName($accion);

    $newEntry = [
        "plant_num" => $plant_num,
        "fecha" => $fecha,
        "usuario" => $user,
        "accion" => $readableAction,
        "detalles" => $detalles,
        "old_value" => $old_value,
        "new_value" => $new_value
    ];
    $history[] = $newEntry;
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(["success" => "Cambio registrado."]);
} else {
    http_response_code(400);
    echo json_encode(["error" => "Solicitud inválida."]);
}

function formatActionName($action) {
    $actionMap = [
        'identificacion' => 'Cambio de nombre',
        'descripcion' => 'Descripción',
        'estado' => 'Estado',
        'riego' => 'Riego',
        'sistema_riego' => 'Sistema de riego',
        'subida_imagen' => 'Nueva imagen',
        'eliminacion_imagen' => 'Imagen eliminada',
        'creacion_planta' => 'Nueva planta'
    ];
    return isset($actionMap[$action]) ? $actionMap[$action] : ucfirst($action);
}
function resumen($txt) {
    $txt = trim(str_replace(["\n","\r"], " ", $txt));
    return mb_strlen($txt) > 60 ? mb_substr($txt,0,57).'…' : $txt;
}
?>