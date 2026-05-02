<?php
header('Content-Type: text/plain');
echo "=== Server Diagnostics ===\n\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "OS: " . PHP_OS . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "\n\n";

echo "=== Required Extensions ===\n";
$required = ['gd', 'curl', 'openssl', 'exif', 'mbstring', 'json'];
foreach ($required as $ext) {
    $loaded = extension_loaded($ext) ? '✅ OK' : '❌ MISSING';
    echo "  $ext: $loaded\n";
}

if (extension_loaded('gd')) {
    echo "\n=== GD Details ===\n";
    $gd = gd_info();
    echo "  JPEG: " . ($gd['JPEG Support'] ? '✅' : '❌') . "\n";
    echo "  PNG:  " . ($gd['PNG Support'] ? '✅' : '❌') . "\n";
    echo "  WebP: " . ($gd['WebP Support'] ? '✅' : '❌') . "\n";
}

echo "\n=== Upload Limits ===\n";
echo "  upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "  post_max_size: " . ini_get('post_max_size') . "\n";
echo "  memory_limit: " . ini_get('memory_limit') . "\n";
