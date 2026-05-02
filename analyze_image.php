<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/includes/app.php';

// Catch fatal errors and return JSON instead of empty 500
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode([
            "success" => false,
            "error" => "Error fatal en el servidor: " . $err['message']
        ]);
    }
});

// Authentication Check
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    header('Content-Type: application/json'); 
    echo json_encode(["success" => false, "error" => "Acceso denegado. Se requiere inicio de sesión."]);
    exit;
}

if (!extension_loaded('curl')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "error" => "Error del servidor: La extensión PHP cURL no está instalada o habilitada. Contacta al administrador del hosting."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["image_path"]) && isset($_POST["plant_num"])) {
    $image_path = $_POST["image_path"];
    $plant_num = intval($_POST["plant_num"]);
    
    if (!file_exists($image_path)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "error" => "Archivo de imagen no encontrado en la ruta especificada: " . htmlspecialchars($image_path)]);
        exit;
    }
    
    $owner = app_current_user();
    $plantData = $owner ? app_fetch_plant($owner, $plant_num) : null;
    
    if (!$plantData) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "error" => "Planta número " . htmlspecialchars((string) $plant_num) . " no encontrada."]);
        exit;
    }
    
    $aiImageDir = __DIR__ . '/images';
    if (!is_dir($aiImageDir)) {
        if (!@mkdir($aiImageDir, 0755, true)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(["success" => false, "error" => "No se pudo crear el directorio para imágenes temporales AI."]);
            exit;
        }
    }
    // Use session_id for unique temp file name per user session to avoid conflicts
    $aiImagePath = $aiImageDir . "/ai_temp_" . session_id() . "_" . time() . ".webp";
    
    $finalImagePath = $image_path;
    $isTempFile = false;

    // Check if GD is loaded for resizing
    $useGD = extension_loaded('gd');
    
    if ($useGD) {
        if (cropAndResizeImage($image_path, $aiImagePath, 512)) {
            $finalImagePath = $aiImagePath;
            $isTempFile = true;
        } else {
            // Resize failed, log it but try with original image
            error_log("AI Analysis: Image resize failed, falling back to original image: " . $image_path);
        }
    } else {
        // GD not loaded, use original image (warning: higher token usage)
         error_log("AI Analysis: GD extension missing. Using original image (high token usage).");
    }
    
    try {
        // Guard: avoid fatal memory errors on huge images
        $sizeCheck = @filesize($finalImagePath);
        if ($sizeCheck !== false && $sizeCheck > 6 * 1024 * 1024) { // 6MB
            throw new Exception("Imagen demasiado grande para análisis. Reduce el tamaño o habilita GD.");
        }

        $analysis = sendImageToOpenAI($finalImagePath, $plantData);

        if ($isTempFile && file_exists($finalImagePath)) {
            @unlink($finalImagePath);
        }

        header('Content-Type: application/json');
        echo json_encode([
            "success" => true, 
            "results" => $analysis
        ]);
        exit;

    } catch (Exception $e) {
        if ($isTempFile && file_exists($finalImagePath)) {
            @unlink($finalImagePath);
        }

        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "error" => "Excepción en análisis AI: " . $e->getMessage()]);
        exit;
    }
    
} else {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "error" => "Solicitud inválida. Se requiere image_path y plant_num vía POST."]);
}

function cropAndResizeImage($sourcePath, $targetPath, $size = 512) {
    if (!extension_loaded('gd')) return false;

    list($width, $height, $type) = @getimagesize($sourcePath);
    if (!$width || !$height) {
        error_log("Failed to get image size for: " . $sourcePath);
        return false;
    }
    
    $cropSize = min($width, $height);
    $x = ($width - $cropSize) / 2;
    $y = ($height - $cropSize) / 2;
    
    $sourceImage = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $sourceImage = @imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = @imagecreatefrompng($sourcePath);
            if ($sourceImage) {
                imagepalettetotruecolor($sourceImage); // Convert palette to true color
                imagealphablending($sourceImage, true); // Enable alpha blending
                imagesavealpha($sourceImage, true); // Save alpha channel
            }
            break;
        case IMAGETYPE_WEBP:
            $sourceImage = @imagecreatefromwebp($sourcePath);
            break;
        default:
            error_log("Unsupported image type for AI processing: " . image_type_to_mime_type($type));
            return false;
    }
    
    if (!$sourceImage) {
        error_log("Failed to create image resource from: " . $sourcePath . " (type: " . $type . ")");
        return false;
    }
    
    $targetImage = @imagecreatetruecolor($size, $size);
    if (!$targetImage) {
        error_log("Failed to create target image resource.");
        return false;
    }

    // For PNG and WEBP, ensure transparency is handled correctly for the new image
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
        imagealphablending($targetImage, false); // Disable blending for the target
        imagesavealpha($targetImage, true);      // Save alpha channel for the target
        $transparentColor = imagecolorallocatealpha($targetImage, 0, 0, 0, 127); // Fully transparent
        imagefill($targetImage, 0, 0, $transparentColor); // Fill with transparent background
    }
    
    @imagecopyresampled(
        $targetImage, $sourceImage,
        0, 0, (int)$x, (int)$y,
        $size, $size, (int)$cropSize, (int)$cropSize
    );
    
    $result = @imagewebp($targetImage, $targetPath, 80); // Quality 80 is a good balance
    
    if (!$result) {
        error_log("Failed to save processed image to: " . $targetPath);
    }
    return $result;
}

function sendImageToOpenAI($imagePath, $plantData) {
    $api_key = app_env('OPENAI_API_KEY');
    if (app_is_placeholder($api_key)) {
        throw new Exception('Configura OPENAI_API_KEY en .env');
    }
    $imageData = base64_encode(file_get_contents($imagePath));

    // Determine mime type
    $mimeType = "image/jpeg"; // Default
    if (function_exists('getimagesize')) {
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo && isset($imageInfo['mime'])) {
            $mimeType = $imageInfo['mime'];
        }
    } else {
        // Fallback by extension if getimagesize fails or is disabled (unlikely)
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        if ($ext === 'png') $mimeType = "image/png";
        elseif ($ext === 'webp') $mimeType = "image/webp";
        elseif ($ext === 'gif') $mimeType = "image/gif";
    }

    // Prevent memory fatal errors with very large files (esp. without GD)
    $imageSize = @filesize($imagePath);
    if ($imageSize !== false && $imageSize > 6 * 1024 * 1024) { // 6MB
        throw new Exception("Imagen demasiado grande para análisis. Reduce el tamaño o habilita GD.");
    }
    // Safety check for empty or missing file
    $content = @file_get_contents($imagePath);
    if ($content === false) {
       throw new Exception("No se pudo leer el archivo de imagen: $imagePath");
    }
    $imageData = base64_encode($content);

    $prompt = <<<PROMPT
Analiza la imagen de la planta y responde en formato JSON con las siguientes claves:
- "estado": Explica en una o dos frases el estado de salud actual de la planta.
- "identificacion": Devuelve SIEMPRE dos líneas: la primera línea el nombre común más probable, la segunda línea el nombre científico (o subespecie) más probable. Si no sabes el nombre científico, deja la segunda línea vacía. Ejemplo: "Rosa\nRosa sp.".
- "descripcion": Da una breve descripción general de la planta (tipo, forma, color, tamaño, etc.), sin valorar su estado de salud.
Ten en cuenta que la planta está en Barcelona, España (clima mediterráneo, plantas ornamentales y de jardín típicas de la región).
Responde SOLO el JSON, sin explicaciones ni texto adicional.
PROMPT;

    $payload = [
        "model" => "gpt-5-mini-2025-08-07",
        "messages" => [
            [
                "role" => "user",
                "content" => [
                    ["type" => "text", "text" => $prompt],
                    ["type" => "image_url", "image_url" => [
                        "url" => "data:" . $mimeType . ";base64," . $imageData,
                        "detail" => "high"
                    ]]
                ]
            ]
        ],
        "max_completion_tokens" => 2000, 
        // "response_format" => [ "type" => "json_object" ] // Comment out for future model compatibility
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15); // Connection timeout: 15 seconds
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);      // Total timeout: 90 seconds for OpenAI response

    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);

    if ($curl_error) {
        throw new Exception('cURL error: ' . $curl_error);
    }

    $responseData = json_decode($response_body, true);

    if ($http_code >= 400 || !isset($responseData['choices'][0]['message']['content'])) {
        $api_error_message = 'Error desconocido de la API de OpenAI.';
        if (isset($responseData['error']['message'])) {
            $api_error_message = $responseData['error']['message'];
        } elseif (is_string($response_body) && strlen($response_body) < 500) {
            $api_error_message = "Respuesta inesperada de la API (HTTP $http_code): " . substr(htmlspecialchars($response_body), 0, 200);
        }
        error_log("OpenAI API Error ($http_code): " . $api_error_message . " | Payload: " . json_encode($payload) . " | Response: " . $response_body);
        throw new Exception('Error de la API de OpenAI: ' . $api_error_message);
    }
    
    $aiJsonContent = $responseData['choices'][0]['message']['content'];
    $json = json_decode($aiJsonContent, true);

    if (!is_array($json)) {
        // Fallback if JSON is not directly in content (e.g. model adds text or markdown)
        if (preg_match('/```json\s*(\{[\s\S]*?\})\s*```/i', $aiJsonContent, $matches)) {
            $json = json_decode($matches[1], true);
        } elseif (preg_match('/(\{[\s\S]*\})/', $aiJsonContent, $matches)) {
            $json = json_decode($matches[1], true);
        }
        if (!is_array($json)) {
            error_log("Failed to parse JSON from AI response: " . $aiJsonContent);
            throw new Exception('No se pudo extraer JSON válido del análisis AI. Respuesta: ' . substr(htmlspecialchars($aiJsonContent), 0, 200));
        }
    }

    // Ensure all expected keys exist, providing defaults if not.
    $estado = isset($json['estado']) ? trim($json['estado']) : "No se pudo determinar el estado.";
    $identificacion = isset($json['identificacion']) ? trim($json['identificacion']) : "No identificado.";
    $descripcion = isset($json['descripcion']) ? trim($json['descripcion']) : "Sin descripción.";

    return [
        'estado' => $estado,
        'identificacion' => $identificacion,
        'descripcion' => $descripcion
    ];
}
?>
