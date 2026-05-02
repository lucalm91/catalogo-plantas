<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['zone_image']) || !isset($_POST['zona'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Solicitud inválida']);
    exit;
}

$zona = trim($_POST['zona']);
if (empty($zona)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Zona no especificada']);
    exit;
}

$uploadedFile = $_FILES['zone_image'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

if (!in_array($uploadedFile['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido']);
    exit;
}

if ($uploadedFile['size'] > 10 * 1024 * 1024) { // 10MB limit
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Archivo demasiado grande']);
    exit;
}

try {
    $imagesDir = __DIR__ . '/images';
    if (!is_dir($imagesDir)) {
        if (!mkdir($imagesDir, 0755, true)) {
            throw new Exception('No se pudo crear el directorio de imágenes');
        }
    }
    
    $ext = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
    
    // Check if GD is loaded
    if (extension_loaded('gd')) {
        // --- WITH GD: Convert to WebP ---
        $fileName = "zone_" . md5($zona) . ".webp";
        $targetPath = $imagesDir . '/' . $fileName;

        $sourceImage = null;
        switch ($uploadedFile['type']) {
            case 'image/jpeg':
            case 'image/jpg':
                $sourceImage = imagecreatefromjpeg($uploadedFile['tmp_name']);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($uploadedFile['tmp_name']);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($uploadedFile['tmp_name']);
                break;
        }

        if (!$sourceImage) {
            // Fallback if GD fails to open image
             throw new Exception('Error al procesar la imagen con GD.');
        }

        if (!imagewebp($sourceImage, $targetPath, 85)) {
            throw new Exception('Error al guardar la imagen WebP.');
        }

    } else {
        // --- WITHOUT GD: Save original file ---
        $fileName = "zone_" . md5($zona) . "." . $ext;
        $targetPath = $imagesDir . '/' . $fileName;
        
        if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
            throw new Exception('Error al mover la imagen subida.');
        }
        
        // Try to remove old files with same hash but different extensions to avoid confusion?
        // It's a nice to have, but index.php priority loop handles it (it finds the first one).
        // To be safe, we could check loops in index.php order but that's overkill for now.
    }
    
    echo json_encode([
        'success' => true, 
        'image' => 'images/' . $fileName,
        'message' => 'Imagen de zona actualizada correctamente'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>