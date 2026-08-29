<?php

declare(strict_types=1);

/**
 * MySQL setup helper — import schema, migrate legacy JSON, or verify connection.
 *
 * Usage (on server via SSH):
 *   1. Create DB + user in aaPanel
 *   2. mysql -u USER -p DATABASE < database/schema.sql
 *   3. Copy config/config.local.php.example → config/config.local.php (set DB password)
 *   4. php tools/setup-mysql.php
 */

$config = require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/app/bootstrap.php';

use App\JsonDatabase;
use App\Storage\DatabaseInstaller;
use App\Storage\StorageBootstrap;
use App\Storage\StorageFactory;
use App\Storage\StorageKeys;

$driver = strtolower((string) ($config['storage']['driver'] ?? 'json'));
if ($driver !== 'mysql') {
    fwrite(STDERR, "Set storage.driver to mysql in config/config.local.php first.\n");
    exit(1);
}

try {
    DatabaseInstaller::ensureSchema($config);
    $mysql = StorageFactory::create($config);
} catch (Throwable $e) {
    fwrite(STDERR, "MySQL connection failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Check config/config.local.php — database, username, and password must match aaPanel.\n");
    exit(1);
}

echo "MySQL connection OK.\n";
echo "Table app_storage ready.\n";

StorageBootstrap::ensureInitialized($mysql, $config);
echo "Seeded empty stores from database/seeds/ (if any were missing).\n";

$jsonPath = rtrim($config['storage']['path'], '/\\');
if (is_dir($jsonPath)) {
    $json = new JsonDatabase($config);
    $imported = 0;

    foreach (StorageKeys::primaryStores() as $store) {
        if (!$json->exists($store) || $mysql->exists($store)) {
            continue;
        }
        $data = $json->read($store);
        if ($mysql->write($store, $data)) {
            $imported++;
            echo "Migrated from JSON: {$store}\n";
        }
    }

    $resultsDir = $jsonPath . '/results';
    if (is_dir($resultsDir)) {
        foreach (glob($resultsDir . '/*.json') ?: [] as $file) {
            $token = basename($file, '.json');
            if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
                continue;
            }
            $store = StorageKeys::result($token);
            if ($mysql->exists($store)) {
                continue;
            }
            $raw = file_get_contents($file);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($data)) {
                continue;
            }
            if ($mysql->write($store, $data)) {
                $imported++;
                echo "Migrated result: {$store}\n";
            }
        }
    }

    echo "Legacy JSON migration imported {$imported} store(s).\n";
}

foreach (StorageKeys::primaryStores() as $store) {
    $status = $mysql->exists($store) ? 'present' : 'missing';
    echo "  [{$status}] {$store}\n";
}

$all = DatabaseInstaller::listStores($config);
echo "\nRows in app_storage: " . count($all) . "\n";
if ($all !== []) {
    echo "Keys: " . implode(', ', $all) . "\n";
}

echo "\nDone. Admin settings, ads, and FAQ now live in MySQL.\n";
