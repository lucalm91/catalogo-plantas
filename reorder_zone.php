<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

function getUserPlantsFile() {
    if (isset($_SESSION['user'])) {
        $user = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['user']);
        $file = "plants_$user.json";
        if (file_exists($file)) return $file;
        // If user-specific file doesn't exist but base plants.json does, copy it
        if (file_exists("plants.json")) {
            copy("plants.json", $file);
            return $file;
        }
    }
    // Fallback or if plants.json also doesn't exist (though should be handled by getUserPlantsFile in index)
    return "plants.json"; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zona']) && isset($_POST['direction'])) {
    $userPlantsFile = getUserPlantsFile();
    $target_zone_name = $_POST['zona'];
    $direction = $_POST['direction'];
    
    try {
        $plantas_json_content = file_get_contents($userPlantsFile);
        if ($plantas_json_content === false) {
            echo json_encode(['success' => false, 'error' => 'No se pudo leer el archivo de plantas.']);
            exit;
        }
        $plantas = json_decode($plantas_json_content, true);
        if (!is_array($plantas)) {
             $plantas = []; // Handle empty or invalid JSON
        }

        // 1. Group plants by zone
        $zones_grouped = [];
        foreach ($plantas as $planta) {
            $zone_name = $planta['zona'];
            if (!isset($zones_grouped[$zone_name])) {
                $zones_grouped[$zone_name] = ['plants' => [], 'orden_zona' => isset($planta['orden_zona']) ? intval($planta['orden_zona']) : 9999];
            }
            $zones_grouped[$zone_name]['plants'][] = $planta;
            // Ensure orden_zona is consistently taken from the first encountered or existing
            if (isset($planta['orden_zona']) && $zones_grouped[$zone_name]['orden_zona'] == 9999) {
                 $zones_grouped[$zone_name]['orden_zona'] = intval($planta['orden_zona']);
            }
        }
        
        // If a zone has no plants, it might not be in $zones_grouped. Add it if it's the target zone.
        if (!isset($zones_grouped[$target_zone_name]) && count($plantas) === 0) {
             // This case is tricky if the target zone is new and has no plants.
             // For now, reordering implies existing zones with plants.
             // If a zone exists with no plants, its 'orden_zona' might not be defined from a plant.
        }


        // 2. Sort zones by 'orden_zona' to match display order
        uasort($zones_grouped, function($a, $b) {
            return $a['orden_zona'] <=> $b['orden_zona'];
        });
        
        $ordered_zone_names = array_keys($zones_grouped);
        
        $current_idx = array_search($target_zone_name, $ordered_zone_names);
        
        if ($current_idx === false) {
            echo json_encode(['success' => false, 'error' => 'Zona no encontrada en la lista ordenada.']);
            exit;
        }
        
        $swap_idx = ($direction === 'up') ? $current_idx - 1 : $current_idx + 1;
        
        if ($swap_idx < 0 || $swap_idx >= count($ordered_zone_names)) {
            echo json_encode(['success' => false, 'error' => 'No se puede mover más la zona.']);
            exit;
        }
        
        $zone_to_swap_with_name = $ordered_zone_names[$swap_idx];

        // 3. Update 'orden_zona' for all plants in the two affected zones
        // The new 'orden_zona' values will be their new indices in the sorted list
        for ($i = 0; $i < count($plantas); $i++) {
            if ($plantas[$i]['zona'] === $target_zone_name) {
                $plantas[$i]['orden_zona'] = $swap_idx;
            } elseif ($plantas[$i]['zona'] === $zone_to_swap_with_name) {
                $plantas[$i]['orden_zona'] = $current_idx;
            }
        }
        
        // Guardar
        if (file_put_contents($userPlantsFile, json_encode($plantas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo de plantas.']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error al reordenar zona: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Parámetros faltantes o método incorrecto.']);
}
?>
