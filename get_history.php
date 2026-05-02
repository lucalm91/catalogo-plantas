<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["plant_num"])) {
    $plant_num = intval($_GET["plant_num"]);
    
    $historyFile = "history/plant_history.json";
    if (file_exists($historyFile)) {
        $history = json_decode(file_get_contents($historyFile), true);
        if (!is_array($history)) {
            echo json_encode([]);
            exit;
        }
        
        // Filter for the requested plant
        $filteredHistory = array_filter($history, function($entry) use ($plant_num) {
            return $entry['plant_num'] == $plant_num;
        });
        
        // Sort by date, newest first
        usort($filteredHistory, function($a, $b) {
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });
        
        // Format dates for display and include old_value and new_value
        foreach ($filteredHistory as &$entry) {
            if (isset($entry['fecha'])) {
                $timestamp = strtotime($entry['fecha']);
                if ($timestamp) {
                    $entry['fecha'] = date('d/m/Y H:i', $timestamp);
                }
            }
            if (isset($entry['old_value']) && isset($entry['new_value'])) {
                $entry['detalle'] = "De {$entry['old_value']} a {$entry['new_value']}";
            }
        }
        
        // Return as indexed array
        echo json_encode(array_values($filteredHistory));
    } else {
        echo json_encode([]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Solicitud inválida."]);
}
?>