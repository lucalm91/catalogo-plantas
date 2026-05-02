<?php
session_start();
header('Content-Type: application/json');

// For debugging
error_log("delete_log.php called with: " . print_r($_POST, true));

// Check authentication
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(["error" => "Acceso denegado."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["plant_num"]) && isset($_POST["fecha"])) {
    $plantNum = intval($_POST["plant_num"]);
    $fecha = $_POST["fecha"];
    
    $historyFile = "history/plant_history.json";
    if (!file_exists($historyFile)) {
        http_response_code(404);
        echo json_encode(["error" => "Archivo de historial no encontrado."]);
        exit;
    }
    
    $history = json_decode(file_get_contents($historyFile), true);
    if (!is_array($history)) {
        http_response_code(500);
        echo json_encode(["error" => "Formato de historial inválido."]);
        exit;
    }
    
    // Log what we're looking for to help debug
    error_log("Looking for entry with plant_num=$plantNum and fecha=$fecha");
    
    // Find the entry to delete, handle date format differently
    $deleteIndex = -1;
    foreach ($history as $index => $entry) {
        // Format the stored date the same way get_history.php does
        $entryDate = isset($entry['fecha']) ? $entry['fecha'] : '';
        $formattedDate = '';
        
        if ($entryDate) {
            $timestamp = strtotime($entryDate);
            if ($timestamp) {
                $formattedDate = date('d/m/Y H:i', $timestamp);
            }
        }
        
        error_log("Comparing: Entry plant_num={$entry['plant_num']}, fecha=$entryDate, formatted as $formattedDate");
        
        // Compare using both raw date and formatted date
        if ($entry['plant_num'] == $plantNum && 
            ($entry['fecha'] == $fecha || $formattedDate == $fecha)) {
            $deleteIndex = $index;
            error_log("Match found at index $index");
            break;
        }
    }
    
    if ($deleteIndex === -1) {
        error_log("No matching entry found in history");
        http_response_code(404);
        echo json_encode(["error" => "Entrada de historial no encontrada."]);
        exit;
    }
    
    // Remove the log entry
    array_splice($history, $deleteIndex, 1);
    
    // Save the updated history
    if (file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT)) === false) {
        http_response_code(500);
        echo json_encode(["error" => "No se pudo guardar el historial."]);
        exit;
    }
    
    echo json_encode(["success" => "Entrada de historial eliminada."]);
} else {
    http_response_code(400);
    echo json_encode(["error" => "Solicitud inválida."]);
}
?>