<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}
$user = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['user']);
$jsonFile = "plants_$user.json";
if (!file_exists($jsonFile)) {
    if (file_exists("plants.json")) {
        copy("plants.json", $jsonFile);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se encuentra el archivo de plantas']);
        exit;
    }
}
$plant_num = isset($_POST['plant_num']) ? intval($_POST['plant_num']) : 0;
$zona = isset($_POST['zona']) ? $_POST['zona'] : '';
$direction = isset($_POST['direction']) ? $_POST['direction'] : '';
if (!$plant_num || !$zona || !in_array($direction, ['up','down'])) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}
$data = json_decode(file_get_contents($jsonFile), true);
if (!is_array($data)) $data = [];

// Obtener plantas de la zona y su orden
$plants_in_zone = [];
foreach ($data as $p) {
    if ($p['zona'] === $zona) $plants_in_zone[] = $p;
}
usort($plants_in_zone, function($a, $b) {
    $oa = isset($a['orden']) ? intval($a['orden']) : 9999;
    $ob = isset($b['orden']) ? intval($b['orden']) : 9999;
    return $oa <=> $ob;
});
$idx = -1;
foreach ($plants_in_zone as $i => $p) {
    if ($p['num'] == $plant_num) {
        $idx = $i;
        break;
    }
}
if ($idx === -1) {
    echo json_encode(['success' => false, 'error' => 'Planta no encontrada']);
    exit;
}
if (($direction === 'up' && $idx === 0) || ($direction === 'down' && $idx === count($plants_in_zone)-1)) {
    echo json_encode(['success' => false, 'error' => 'No se puede mover más']);
    exit;
}
// Intercambiar orden con la planta anterior/siguiente
$swapIdx = $direction === 'up' ? $idx-1 : $idx+1;
$tmpOrden = $plants_in_zone[$idx]['orden'];
$plants_in_zone[$idx]['orden'] = $plants_in_zone[$swapIdx]['orden'];
$plants_in_zone[$swapIdx]['orden'] = $tmpOrden;

// Actualizar el array global de plantas
foreach ($plants_in_zone as $p) {
    foreach ($data as &$pl) {
        if ($pl['num'] == $p['num']) {
            $pl['orden'] = $p['orden'];
            break;
        }
    }
}
unset($pl);

if (file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'No se pudo guardar el orden']);
}
