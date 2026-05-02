<?php
session_start();
if (!isset($_SESSION['user'])) {
  echo json_encode(['success' => false, 'error' => 'No autorizado']);
  exit;
}

// --- NUEVO: archivo de plantas por usuario ---
$user = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['user']);
$jsonFile = __DIR__ . "/plants_$user.json";
if (!file_exists($jsonFile)) {
  if (file_exists(__DIR__ . "/plants.json")) {
    copy(__DIR__ . "/plants.json", $jsonFile);
  } else {
    echo json_encode(['success' => false, 'error' => 'No se encuentra el archivo de plantas']);
    exit;
  }
}

$num = isset($_POST['num']) ? intval($_POST['num']) : 0;
if (!$num) {
  echo json_encode(['success' => false, 'error' => 'Número de planta inválido']);
  exit;
}

$plants = json_decode(file_get_contents($jsonFile), true);
if (!is_array($plants)) $plants = [];

$found = false;
$newPlants = [];
foreach ($plants as $plant) {
  if (isset($plant['num']) && intval($plant['num']) === $num) {
    $found = true;
    continue;
  }
  $newPlants[] = $plant;
}

if (!$found) {
  echo json_encode(['success' => false, 'error' => 'Planta no encontrada']);
  exit;
}

if (file_put_contents($jsonFile, json_encode($newPlants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
  echo json_encode(['success' => true]);
} else {
  echo json_encode(['success' => false, 'error' => 'No se pudo eliminar la planta']);
}
