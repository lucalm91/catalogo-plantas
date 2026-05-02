<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Function to get user-specific plants file
function getUserPlantsFileChat() {
    if (isset($_SESSION['user'])) {
        $user = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['user']);
        $file = __DIR__ . "/plants_$user.json";
        if (file_exists($file)) return $file;
        // Fallback or copy logic if needed, similar to other files
        if (file_exists(__DIR__ . "/plants.json")) {
             // Optionally copy or just use as a read-only fallback if appropriate
            return __DIR__ . "/plants.json";
        }
    }
    return __DIR__ . "/plants.json"; // Default fallback
}


$msg = trim($_POST['message'] ?? '');
$plant_num = trim($_POST['plant_num'] ?? '');
$history_json = $_POST['history'] ?? '[]'; // Get history from POST
$history = json_decode($history_json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $history = []; // Default to empty history if JSON is invalid
}


// Buscar la imagen principal de la planta para pasarla al contexto de la IA
$image_path = '';
$plant_name = 'esta planta';

if ($plant_num) {
    $userPlantsFile = getUserPlantsFileChat();
    if (file_exists($userPlantsFile)) {
        $plants_data = json_decode(file_get_contents($userPlantsFile), true);
        if (is_array($plants_data)) {
            foreach ($plants_data as $p) {
                if (isset($p['num']) && $p['num'] == $plant_num) {
                    if (!empty($p['imagenes']) && file_exists($p['imagenes'][0])) {
                        $image_path = $p['imagenes'][0];
                    }
                    if (!empty($p['identificacion'])) {
                        $plant_name = explode("\n", $p['identificacion'])[0]; // Get common name
                    }
                    break;
                }
            }
        }
    }
}

if (!$msg) {
    echo json_encode(['error' => 'Mensaje vacío']);
    exit;
}

// Si hay imagen, preparar imagen cuadrada para IA (como en analyze_image.php)
$aiImagePath = '';
if ($image_path && file_exists($image_path)) {
    // Si NO hay GD, usar la imagen original directamente
    if (!extension_loaded('gd')) {
         $aiImagePath = $image_path; 
    } else {
        // Si hay GD, redimensionar para ahorrar tokens
        $aiImageDir = __DIR__ . '/images';
        if (!is_dir($aiImageDir)) {
            @mkdir($aiImageDir, 0755, true);
        }
        $tempPath = $aiImageDir . "/ai_chat_temp_" . session_id() . "_" . time() . ".webp";
        if (cropAndResizeImage($image_path, $tempPath, 512)) {
            $aiImagePath = $tempPath;
        } else {
             // Fallback: usar original si falla el redimensionado
            $aiImagePath = $image_path;
            error_log("AI Chat: Failed to resize image, using original: " . $image_path);
        }
    }
}

// Llamar a OpenAI con el mensaje y la imagen (si hay)
try {
    // Si usó una imagen temporal (redimensionada), recordarla para borrarla
    $isTempImage = ($aiImagePath !== $image_path); 

    $reply = sendToOpenAI($msg, $aiImagePath, $plant_name, $history);
    
    if ($isTempImage && file_exists($aiImagePath)) {
        @unlink($aiImagePath);
    }
    echo json_encode(['reply' => $reply]);
} catch (Exception $e) {
    if (isset($isTempImage) && $isTempImage && file_exists($aiImagePath)) {
        @unlink($aiImagePath);
    }
    http_response_code(500);
    echo json_encode(['error' => 'Error al contactar con la IA: ' . $e->getMessage()]);
}

// --- FUNCIONES AUXILIARES ---

function cropAndResizeImage($sourcePath, $targetPath, $size = 512) {
    list($width, $height, $type) = @getimagesize($sourcePath);
    if (!$width || !$height) return false;
    
    $cropSize = min($width, $height);
    $x = ($width - $cropSize) / 2;
    $y = ($height - $cropSize) / 2;
    
    $sourceImage = null;
    switch ($type) {
        case IMAGETYPE_JPEG: $sourceImage = @imagecreatefromjpeg($sourcePath); break;
        case IMAGETYPE_PNG:
            $sourceImage = @imagecreatefrompng($sourcePath);
            if ($sourceImage) {
                imagepalettetotruecolor($sourceImage);
                imagealphablending($sourceImage, true);
                imagesavealpha($sourceImage, true);
            }
            break;
        case IMAGETYPE_WEBP: $sourceImage = @imagecreatefromwebp($sourcePath); break;
        default: return false;
    }
    
    if (!$sourceImage) return false;
    
    $targetImage = @imagecreatetruecolor($size, $size);
    if (!$targetImage) { return false; }

    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparentColor = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        imagefill($targetImage, 0, 0, $transparentColor);
    }
    
    @imagecopyresampled($targetImage, $sourceImage, 0, 0, (int)$x, (int)$y, $size, $size, (int)$cropSize, (int)$cropSize);
    $result = @imagewebp($targetImage, $targetPath, 80);
    
    return $result;
}

function sendToOpenAI($userMessage, $imagePath = null, $plantName = 'esta planta', $chatHistory = []) {
    $api_key = "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"; // User's API key

    $messages = [];

    // System Prompt (Context) - Changed to 'developer' for newer models or keep as user if safer
    // For broad compatibility with o1/gpt-5 type models, avoiding 'system' if it fails. 
    // But usually 'user' is the safest fallback if 'system' is rejected.
    $messages[] = [
        "role" => "user", 
        "content" => "Instrucción de sistema: Eres un asistente experto en plantas y jardinería. La planta actual se llama '$plantName'. Estás en Barcelona, España (clima mediterráneo). Proporciona respuestas concisas y útiles. Si te piden identificar una planta en una imagen, hazlo lo mejor posible."
    ];

    // Add existing chat history
    foreach ($chatHistory as $entry) {
        $messages[] = [
            "role" => $entry['role'],
            "content" => $entry['content']
        ];
    }

    // Current user message content
    $currentUserMessageContent = [["type" => "text", "text" => $userMessage]];

    if ($imagePath && file_exists($imagePath)) {
        $imageData = base64_encode(file_get_contents($imagePath));
        
        // Determine mime type
        $mimeType = "image/jpeg"; // Default
        if (function_exists('getimagesize')) {
            $imageInfo = @getimagesize($imagePath);
            if ($imageInfo && isset($imageInfo['mime'])) {
                $mimeType = $imageInfo['mime'];
            }
        } else {
            $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            if ($ext === 'png') $mimeType = "image/png";
            elseif ($ext === 'webp') $mimeType = "image/webp";
            elseif ($ext === 'gif') $mimeType = "image/gif";
        }

        $currentUserMessageContent[] = [
            "type" => "image_url",
            "image_url" => [
                "url" => "data:" . $mimeType . ";base64," . $imageData,
                "detail" => "high" // Added detail: "high"
            ]
        ];
    }

    $messages[] = [
        "role" => "user",
        "content" => $currentUserMessageContent
    ];
    
    $payload = [
        "model" => "gpt-5-mini-2025-08-07",
        "messages" => $messages,
        "max_completion_tokens" => 2000,
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
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

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
        error_log("OpenAI Chat API Error ($http_code): " . $api_error_message . " | Payload: " . json_encode($payload) . " | Response: " . $response_body);
        throw new Exception('Error de la API de OpenAI: ' . $api_error_message);
    }
    
    return trim($responseData['choices'][0]['message']['content']);
}
