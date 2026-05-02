<?php
// filepath: \\synology_ds220\web\home-dashboard\plantas\update_zone_order.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ordered_zones'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Solicitud inválida']);
    exit;
}

$user = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['user']);
$jsonFile = __DIR__ . "/plants_$user.json";

if (!file_exists($jsonFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Archivo de plantas no encontrado']);
    exit;
}

try {
    $orderedZones = json_decode($_POST['ordered_zones'], true);
    if (!is_array($orderedZones)) {
        throw new Exception('Datos de orden inválidos');
    }
    
    $plants = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($plants)) {
        throw new Exception('Error al leer plantas');
    }
    
    // Update orden_zona for each plant based on zone order
    foreach ($plants as &$plant) {
        $zoneIndex = array_search($plant['zona'], $orderedZones);
        if ($zoneIndex !== false) {
            $plant['orden_zona'] = $zoneIndex;
        }
    }
    
    // Reorder plants array based on the new orden_zona values
    usort($plants, function($a, $b) {
        return $a['orden_zona'] <=> $b['orden_zona'];
    });
    
    if (file_put_contents($jsonFile, json_encode($plants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Error al guardar archivo');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>