<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(["error" => "Acceso denegado."]);
    exit;
}
// --- NUEVO: archivo de plantas por usuario ---
$user = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['user']);
$jsonFile = "plants_$user.json";
if (!file_exists($jsonFile)) {
    if (file_exists("plants.json")) {
        copy("plants.json", $jsonFile);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "No se encuentra el archivo de plantas"]);
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["plant_num"]) && isset($_POST["field"]) && isset($_POST["value"])) {
    $plant_num = intval($_POST["plant_num"]);
    $field = $_POST["field"];
    $value = $_POST["value"];
    
    $allowed_fields = ["identificacion", "estado", "descripcion", "zona", "riego", "sistema_riego"];
    if (!in_array($field, $allowed_fields)) {
        http_response_code(400);
        echo json_encode(["error" => "Campo no permitido."]);
        exit;
    }
    
    $data = json_decode(file_get_contents($jsonFile), true);
    $found = false;
    foreach ($data as &$planta) {
        if ($planta['num'] == $plant_num) {
            $planta[$field] = $value;
            $found = true;
            break;
        }
    }
    if ($found) {
        file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
        echo json_encode(["success" => "Campo actualizado."]);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Planta no encontrada."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Solicitud inválida."]);
}
?>
