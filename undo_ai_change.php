<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(["error" => "Acceso denegado."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["plant_num"]) && isset($_POST["fecha"])) {
    $plant_num = intval($_POST["plant_num"]);
    $fecha = $_POST["fecha"];
    $jsonFile = "plants.json";
    $historyDir = "history/";
    $historyFile = $historyDir . "plant_history.json";

    if (!file_exists($historyFile)) {
        echo json_encode(["error" => "Archivo de historial no encontrado."]);
        exit;
    }
    $history = json_decode(file_get_contents($historyFile), true);
    $plants = json_decode(file_get_contents($jsonFile), true);

    // Buscar la entrada por fecha y old_value
    $log = null;
    for ($i = count($history) - 1; $i >= 0; $i--) {
        $entry = $history[$i];
        $entryDate = isset($entry['fecha']) ? $entry['fecha'] : '';
        $formattedDate = '';
        if ($entryDate) {
            $timestamp = strtotime($entryDate);
            if ($timestamp) {
                $formattedDate = date('d/m/Y H:i', $timestamp);
            }
        }
        if ($entry['plant_num'] == $plant_num && ($entry['fecha'] == $fecha || $formattedDate == $fecha)) {
            if (
                (isset($entry['old_value']) && $entry['old_value'] !== null && $entry['old_value'] !== '') &&
                (isset($entry['accion']) && $entry['accion'])
            ) {
                $log = $entry;
                break;
            }
        }
    }
    if (!$log) {
        echo json_encode(["error" => "No se encontró el cambio para deshacer."]);
        exit;
    }

    // Restaurar el campo
    $field = null;
    switch ($log['accion']) {
        case 'Cambio de nombre': $field = 'identificacion'; break;
        case 'Descripción': $field = 'descripcion'; break;
        case 'Estado': $field = 'estado'; break;
        case 'Riego': $field = 'riego'; break;
        case 'Sistema de riego': $field = 'sistema_riego'; break;
        // Permitir también deshacer de "Deshacer ..." (cadenas que empiezan por "Deshacer ")
        default:
            if (strpos($log['accion'], 'Deshacer ') === 0) {
                $accionBase = trim(str_replace('Deshacer', '', $log['accion']));
                if ($accionBase === 'Cambio de nombre') $field = 'identificacion';
                elseif ($accionBase === 'Descripción') $field = 'descripcion';
                elseif ($accionBase === 'Estado') $field = 'estado';
                elseif ($accionBase === 'Riego') $field = 'riego';
                elseif ($accionBase === 'Sistema de riego') $field = 'sistema_riego';
            }
    }
    if (!$field) {
        echo json_encode(["error" => "Campo no permitido para deshacer."]);
        exit;
    }
    $restored = false;
    foreach ($plants as &$planta) {
        if ($planta['num'] == $plant_num) {
            $planta[$field] = $log['old_value'];
            $restored = true;
            break;
        }
    }
    if (!$restored) {
        echo json_encode(["error" => "No se pudo restaurar el valor anterior."]);
        exit;
    }
    // Guardar el cambio en plants.json
    file_put_contents($jsonFile, json_encode($plants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Registrar el deshacer en el historial
    $history[] = [
        "plant_num" => $plant_num,
        "fecha" => date("Y-m-d H:i:s"),
        "usuario" => $_SESSION['user'],
        "accion" => "Deshacer " . $log['accion'],
        "detalles" => "Restaurado a: '" . resumen($log['old_value']) . "'",
        "old_value" => $log['new_value'],
        "new_value" => $log['old_value']
    ];
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Devolver el valor restaurado y el campo para que el frontend lo actualice
    echo json_encode([
        "success" => true,
        "restored_field" => $field,
        "restored_value" => $log['old_value']
    ]);
} else {
    http_response_code(400);
    echo json_encode(["error" => "Solicitud inválida."]);
}

function resumen($txt) {
    $txt = trim(str_replace(["\n","\r"], " ", $txt));
    return mb_strlen($txt) > 60 ? mb_substr($txt,0,57).'…' : $txt;
}
?>