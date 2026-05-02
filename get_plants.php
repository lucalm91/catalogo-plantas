<?php
// filepath: \\synology_ds220\web\home-dashboard\plantas\get_plants.php
session_start();
header('Content-Type: application/json');

function getUserPlantsFile() {
    if (isset($_SESSION['user'])) {
        $user = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['user']);
        $file = "plants_$user.json";
        if (file_exists($file)) return $file;
        if (file_exists("plants.json")) return "plants.json";
    }
    return "plants.json";
}

$jsonFile = getUserPlantsFile();
if (!file_exists($jsonFile)) {
    echo json_encode([]);
    exit;
}

$data = file_get_contents($jsonFile);
echo $data !== false ? $data : '[]';
?>