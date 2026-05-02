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
  // Si no existe, crea una copia inicial desde plants.json
  if (file_exists(__DIR__ . "/plants.json")) {
    copy(__DIR__ . "/plants.json", $jsonFile);
  } else {
    echo json_encode(['success' => false, 'error' => 'No se encuentra el archivo de plantas']);
    exit;
  }
}

$zona = isset($_POST['zona']) ? trim($_POST['zona']) : '';
$identificacion = isset($_POST['identificacion']) ? trim($_POST['identificacion']) : '';
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
$estado = isset($_POST['estado']) ? trim($_POST['estado']) : '';
$riego = isset($_POST['riego']) ? trim($_POST['riego']) : '';
$sistema_riego = isset($_POST['sistema_riego']) ? trim($_POST['sistema_riego']) : '';

if (!$zona || !$identificacion || !$descripcion || !$estado) {
  echo json_encode(['success' => false, 'error' => 'Faltan datos obligatorios']);
  exit;
}

$plants = json_decode(file_get_contents($jsonFile), true);
if (!is_array($plants)) $plants = [];

// Buscar el máximo número de planta
$maxNum = 0;
foreach ($plants as $p) {
  if (isset($p['num']) && is_numeric($p['num']) && $p['num'] > $maxNum) $maxNum = $p['num'];
}
$newNum = $maxNum + 1;

// Calcular el orden máximo en la zona
$maxOrden = -1;
foreach ($plants as $p) {
  if (isset($p['zona']) && $p['zona'] === $zona && isset($p['orden']) && is_numeric($p['orden'])) {
    if ($p['orden'] > $maxOrden) $maxOrden = $p['orden'];
  }
}
$newOrden = $maxOrden + 1;

// Crear la nueva planta
$newPlant = [
  'num' => $newNum,
  'zona' => $zona,
  'identificacion' => $identificacion,
  'descripcion' => $descripcion,
  'estado' => $estado,
  'riego' => $riego,
  'sistema_riego' => $sistema_riego,
  'imagenes' => [],
  'orden' => $newOrden
];

$plants[] = $newPlant;

// Guardar el JSON asegurando que la nueva planta quede al final
if (file_put_contents($jsonFile, json_encode($plants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
  // Recarga el JSON desde disco para asegurar que esté actualizado
  clearstatcache();
  $plantsReloaded = json_decode(file_get_contents($jsonFile), true);
  $newPlantData = null;
  foreach ($plantsReloaded as $p) {
    if ($p['num'] == $newNum) {
      $newPlantData = $p;
      break;
    }
  }
  echo json_encode(['success' => true, 'num' => $newNum, 'plant' => $newPlantData]);
} else {
  echo json_encode(['success' => false, 'error' => 'No se pudo guardar la planta']);
}