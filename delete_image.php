<?php
error_reporting(0); // Suppress warnings/notices
session_start();
header('Content-Type: application/json'); // <-- Add this line

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
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["plant_num"]) && isset($_POST["imagen"])) {
    $plant_num = intval($_POST["plant_num"]);
    $imagenToDelete = $_POST["imagen"];
    
    $data = json_decode(file_get_contents($jsonFile), true);
    $found = false;
    foreach ($data as &$planta) {
        if ($planta['num'] == $plant_num) {
            if (isset($planta['imagenes']) && is_array($planta['imagenes'])) {
                $index = array_search($imagenToDelete, $planta['imagenes']);
                if ($index !== false) {
                    // Eliminar la imagen del array
                    array_splice($planta['imagenes'], $index, 1);
                    $found = true;
                    // Opcional: eliminar el archivo del servidor
                    if (file_exists($imagenToDelete)) {
                        unlink($imagenToDelete);
                    }
                }
            }
            break;
        }
    }
    if ($found) {
        file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(["success" => "Imagen eliminada."]);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Imagen o planta no encontrada."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Solicitud inválida."]);
}
?>
