<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/app.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse desde terminal.\n");
    exit(1);
}

$reset = in_array('--reset', $argv, true);
$installOnly = in_array('--schema-only', $argv, true);

try {
    app_install_schema();

    if ($installOnly) {
        echo "Schema instalado.\n";
        exit(0);
    }

    if ($reset) {
        app_db()->exec('SET FOREIGN_KEY_CHECKS=0');
        app_db()->exec('TRUNCATE TABLE plant_history');
        app_db()->exec('TRUNCATE TABLE plant_images');
        app_db()->exec('TRUNCATE TABLE plants');
        app_db()->exec('SET FOREIGN_KEY_CHECKS=1');
        echo "Tablas vaciadas.\n";
    }

    $plantFiles = [
        'Luca' => app_root() . '/plants_Luca.json',
        'Ale' => app_root() . '/plants_Ale.json',
    ];

    foreach ($plantFiles as $owner => $file) {
        if (!is_file($file)) {
            echo "Saltando $owner: no existe $file\n";
            continue;
        }

        $plants = json_decode((string) file_get_contents($file), true);
        if (!is_array($plants)) {
            throw new RuntimeException("JSON inválido: $file");
        }

        $count = 0;
        foreach ($plants as $plant) {
            $existing = app_fetch_plant($owner, (int) ($plant['num'] ?? 0));
            if ($existing) {
                continue;
            }
            app_create_plant($owner, $plant);
            $count++;
        }
        echo "Importadas $count plantas para $owner.\n";
    }

    $historyFile = app_root() . '/history/plant_history.json';
    if (is_file($historyFile)) {
        $history = json_decode((string) file_get_contents($historyFile), true);
        if (!is_array($history)) {
            throw new RuntimeException("JSON inválido: $historyFile");
        }

        $stmt = app_db()->prepare(
            'INSERT INTO plant_history (owner, plant_num, fecha, usuario, accion, detalles, old_value, new_value)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $imported = 0;
        foreach ($history as $entry) {
            $usuario = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) ($entry['usuario'] ?? 'Luca'));
            $owner = in_array($usuario, ['Luca', 'Ale'], true) ? $usuario : 'Luca';
            $fecha = (string) ($entry['fecha'] ?? date('Y-m-d H:i:s'));
            $timestamp = strtotime($fecha);
            $fechaSql = $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
            $stmt->execute([
                $owner,
                (int) ($entry['plant_num'] ?? 0),
                $fechaSql,
                $usuario ?: $owner,
                (string) ($entry['accion'] ?? ''),
                (string) ($entry['detalles'] ?? ''),
                isset($entry['old_value']) ? (string) $entry['old_value'] : null,
                isset($entry['new_value']) ? (string) $entry['new_value'] : null,
            ]);
            $imported++;
        }
        echo "Importadas $imported entradas de historial.\n";
    }

    echo "Migración terminada.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
