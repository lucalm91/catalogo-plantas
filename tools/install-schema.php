<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/app.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse desde terminal.\n");
    exit(1);
}

try {
    app_install_schema();
    echo "Schema MySQL instalado.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
