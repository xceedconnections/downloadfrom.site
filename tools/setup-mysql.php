<?php

declare(strict_types=1);

/**
 * MySQL setup — verifies connection, schema, seeds, and legacy JSON import.
 *
 * Usage: php tools/setup-mysql.php
 */

$config = require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Storage\StorageFactory;
use App\Storage\StorageKeys;
use App\Storage\StorageStatus;

$localPath = dirname(__DIR__) . '/config/config.local.php';
if (!is_file($localPath)) {
    fwrite(STDERR, "MISSING: config/config.local.php\n");
    fwrite(STDERR, "  cp config/config.local.php.example config/config.local.php\n");
    exit(1);
}

$password = (string) ($config['storage']['mysql']['password'] ?? '');
if ($password === '') {
    fwrite(STDERR, "Set storage.mysql.password in config/config.local.php\n");
    exit(1);
}

try {
    $db = StorageFactory::create($config);
} catch (Throwable $e) {
    fwrite(STDERR, "MySQL setup failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "MySQL connection OK.\n";
echo "Database: " . ($config['storage']['mysql']['database'] ?? '') . "\n\n";

$summary = StorageStatus::summary($config, $db);
$labels = StorageStatus::storeLabels();

foreach (StorageKeys::primaryStores() as $store) {
    $status = ($summary['stores'][$store] ?? false) ? 'present' : 'missing';
    $label = $labels[$store] ?? $store;
    echo "  [{$status}] {$store} — {$label}\n";
}

echo "\nTotal app_storage rows: " . $summary['row_count'] . "\n";
echo "\nDone. Homepage, header, ads, FAQ, and admin all read from MySQL.\n";
