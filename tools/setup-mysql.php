<?php

declare(strict_types=1);

/**
 * MySQL setup — verifies connection, schema, migration, and table counts.
 *
 * Usage: php tools/setup-mysql.php
 */

$config = require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Storage\StorageFactory;
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
$labels = StorageStatus::tableLabels();

foreach ($labels as $table => $label) {
    $count = $summary['tables'][$table] ?? 0;
    echo "  [{$count} rows] {$table} — {$label}\n";
}

echo "\nLegacy app_storage rows (migration backup): " . $summary['legacy_rows'] . "\n";
echo "\nDone. Site data is stored in separate relational tables.\n";
