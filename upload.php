<?php
// Suppress HTML error output - return errors as JSON instead
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

session_start();
header('Content-Type: application/json');

// Check if upload exceeded PHP limits (empty $_FILES and $_POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($_FILES) && empty($_POST)) {
    http_response_code(413);
    echo json_encode(["error" => "El archivo es demasiado grande. Máximo permitido: " . ini_get('upload_max_filesize')]);
    exit;
}

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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["nueva_imagen"]) && isset($_POST["plant_num"])) {

    // Check for upload errors
    if ($_FILES["nueva_imagen"]["error"] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => "El archivo excede el tamaño máximo permitido por el servidor (" . ini_get('upload_max_filesize') . ").",
            UPLOAD_ERR_FORM_SIZE => "El archivo excede el tamaño máximo permitido por el formulario.",
            UPLOAD_ERR_PARTIAL => "El archivo se subió parcialmente.",
            UPLOAD_ERR_NO_FILE => "No se seleccionó ningún archivo.",
            UPLOAD_ERR_NO_TMP_DIR => "Falta la carpeta temporal del servidor.",
            UPLOAD_ERR_CANT_WRITE => "No se pudo escribir el archivo en disco.",
            UPLOAD_ERR_EXTENSION => "Una extensión de PHP detuvo la subida.",
        ];
        $errMsg = $uploadErrors[$_FILES["nueva_imagen"]["error"]] ?? "Error desconocido al subir el archivo (código " . $_FILES["nueva_imagen"]["error"] . ").";
        http_response_code(400);
        echo json_encode(["error" => $errMsg]);
        exit;
    }

  try {
    $plant_num = intval($_POST["plant_num"]);
    $targetDir = "images/";
    $originalsDir = "images/originals/";

    foreach ([$targetDir, $originalsDir] as $dir) {
        if (!is_dir($dir)) mkdir($dir, 0777, true);
    }

    $timestamp = time();
    $imageFileType = strtolower(pathinfo($_FILES["nueva_imagen"]["name"], PATHINFO_EXTENSION));
    $hasGD = extension_loaded('gd');

    if ($hasGD) {
        // --- Con GD: redimensionar y convertir a WebP ---
        $webpName = "plant_{$plant_num}_{$timestamp}.webp";
        $targetFile = $targetDir . $webpName;
        $originalName = "plant_{$plant_num}_{$timestamp}." . $imageFileType;

        $check = getimagesize($_FILES["nueva_imagen"]["tmp_name"]);
        if ($check === false) {
            http_response_code(400);
            echo json_encode(["error" => "El archivo no es una imagen."]);
            exit;
        }

        $srcImage = null;
        if (in_array($imageFileType, ["jpg", "jpeg"])) {
            $srcImage = imagecreatefromjpeg($_FILES["nueva_imagen"]["tmp_name"]);
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($_FILES["nueva_imagen"]["tmp_name"]);
                if ($exif && isset($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3: $srcImage = imagerotate($srcImage, 180, 0); break;
                        case 6: $srcImage = imagerotate($srcImage, -90, 0); break;
                        case 8: $srcImage = imagerotate($srcImage, 90, 0); break;
                    }
                }
            }
        } elseif ($imageFileType == "png") {
            $srcImage = imagecreatefrompng($_FILES["nueva_imagen"]["tmp_name"]);
        } elseif ($imageFileType == "webp") {
            $srcImage = imagecreatefromwebp($_FILES["nueva_imagen"]["tmp_name"]);
        }
        if (!$srcImage) {
            http_response_code(500);
            echo json_encode(["error" => "Error al procesar la imagen."]);
            exit;
        }

        $origWidth = imagesx($srcImage);
        $origHeight = imagesy($srcImage);
        $maxWidth = 1920; $maxHeight = 1080;
        $ratio = $origWidth / $origHeight;
        $newWidth = $origWidth; $newHeight = $origHeight;
        if ($origWidth > $maxWidth || $origHeight > $maxHeight) {
            if ($origWidth / $maxWidth > $origHeight / $maxHeight) {
                $newWidth = $maxWidth; $newHeight = $maxWidth / $ratio;
            } else {
                $newHeight = $maxHeight; $newWidth = $maxHeight * $ratio;
            }
        }
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($newImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        if (!imagewebp($newImage, $targetFile, 80)) {
            http_response_code(500);
            echo json_encode(["error" => "Error al guardar la imagen en formato WebP."]);
            exit;
        }
    } else {
        // --- Sin GD: guardar la imagen original tal cual ---
        $allowedTypes = ["jpg", "jpeg", "png", "webp", "gif"];
        if (!in_array($imageFileType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(["error" => "Tipo de imagen no soportado: " . $imageFileType]);
            exit;
        }
        $savedName = "plant_{$plant_num}_{$timestamp}." . $imageFileType;
        $targetFile = $targetDir . $savedName;
        if (!move_uploaded_file($_FILES["nueva_imagen"]["tmp_name"], $targetFile)) {
            http_response_code(500);
            echo json_encode(["error" => "Error al guardar la imagen."]);
            exit;
        }
    }

    $data = json_decode(file_get_contents($jsonFile), true);
    $found = false;
    foreach ($data as &$planta) {
        if ($planta['num'] == $plant_num) {
            if (!isset($planta['imagenes']) || !is_array($planta['imagenes'])) $planta['imagenes'] = [];
            array_unshift($planta['imagenes'], $targetFile);
            $found = true;
            break;
        }
    }

    if ($found) {
        file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
        // Solo mover original si GD procesó la imagen (si no, ya se movió antes)
        if ($hasGD) {
            $originalName = "plant_{$plant_num}_{$timestamp}." . $imageFileType;
            move_uploaded_file($_FILES["nueva_imagen"]["tmp_name"], $originalsDir . $originalName);
        }
        echo json_encode([
            "success" => "Imagen agregada.",
            "imagen"  => $targetFile
        ]);
    } else {
        unlink($targetFile);
        http_response_code(404);
        echo json_encode(["error" => "Planta no encontrada."]);
    }

  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error interno al procesar la imagen: " . $e->getMessage()]);
    exit;
  }

} else {
    http_response_code(400);
    echo json_encode(["error" => "Solicitud inválida."]);
}