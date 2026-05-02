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
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Archivo de plantas no encontrado']);
    exit;
}

try {
    $zonaToDelete = trim($_POST['zona']);
    
    if (empty($zonaToDelete)) {
        throw new Exception('El nombre de la zona no puede estar vacío');
    }
    
    $plants = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($plants)) {
        throw new Exception('Error al leer plantas');
    }
    
    // Remove all plants from the specified zone
    $plants = array_filter($plants, function($plant) use ($zonaToDelete) {
        return $plant['zona'] !== $zonaToDelete;
    });
    
    // Re-index array to maintain proper JSON structure
    $plants = array_values($plants);
    
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
