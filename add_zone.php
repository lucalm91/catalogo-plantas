<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['zona'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Solicitud inválida']);
    exit;
}

$user = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['user']);
$jsonFile = __DIR__ . "/plants_$user.json";

if (!file_exists($jsonFile)) {
    // Create empty plants file if it doesn't exist
    if (!file_put_contents($jsonFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'No se pudo crear el archivo de plantas']);
        exit;
    }
}

try {
    $newZona = trim($_POST['zona']);
    
    if (empty($newZona)) {
        throw new Exception('El nombre de la zona no puede estar vacío');
    }
    
    $plants = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($plants)) {
        $plants = [];
    }
    
    // Check if zone already exists
    foreach ($plants as $plant) {
        if ($plant['zona'] === $newZona) {
            throw new Exception('Ya existe una zona con ese nombre');
        }
    }
    
    // Add a placeholder plant to create the zone
    $maxNum = 0;
    foreach ($plants as $p) {
        if (isset($p['num']) && is_numeric($p['num']) && $p['num'] > $maxNum) {
            $maxNum = $p['num'];
        }
    }
    
    $newPlant = [
        'num' => $maxNum + 1,
        'zona' => $newZona,
        'identificacion' => 'Nueva planta',
        'descripcion' => 'Descripción pendiente',
        'estado' => 'Estado pendiente',
        'riego' => '',
        'sistema_riego' => '',
        'imagenes' => [],
        'orden' => 0,
        'orden_zona' => count(array_unique(array_column($plants, 'zona')))
    ];
    
    $plants[] = $newPlant;
    
    if (file_put_contents($jsonFile, json_encode($plants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo json_encode(['success' => true, 'plant' => $newPlant]);
    } else {
        throw new Exception('Error al guardar archivo');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
