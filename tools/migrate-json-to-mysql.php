<?php

declare(strict_types=1);

/**
 * One-time import of JSON storage files into MySQL app_storage table.
 *
 * Usage:
 *   1. Create DB and import database/schema.sql
 *   2. Set storage.driver = mysql in config/config.local.php
 *   3. php tools/migrate-json-to-mysql.php
 */

$config = require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/app/bootstrap.php';

use App\JsonDatabase;
use App\Storage\MysqlStorage;
use App\Storage\StorageKeys;

$json = new JsonDatabase($config);
$mysql = new MysqlStorage($config);

$imported = 0;
$skipped = 0;

foreach (StorageKeys::primaryStores() as $store) {
    if (!$json->exists($store)) {
        $skipped++;
        echo "Skip (missing): {$store}\n";
        continue;
    }

    $data = $json->read($store);

    if ($mysql->write($store, $data)) {
        $imported++;
        echo "Imported: {$store}\n";
    } else {
        echo "Failed: {$store}\n";
    }
}

$resultsDir = rtrim($config['storage']['path'], '/\\') . '/results';
if (is_dir($resultsDir)) {
    foreach (glob($resultsDir . '/*.json') ?: [] as $file) {
        $token = basename($file, '.json');
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            continue;
        }
        $raw = file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            $skipped++;
            continue;
        }
        $store = StorageKeys::result($token);
        if ($mysql->write($store, $data)) {
            $imported++;
            echo "Imported: {$store}\n";
        }
    }
}

echo "\nDone. Imported {$imported}, skipped {$skipped}.\n";
echo "Set storage.driver to mysql in config.local.php if not already.\n";
