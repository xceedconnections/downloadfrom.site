<?php

declare(strict_types=1);

namespace App\Storage;

use App\AdminAuth;
use App\Contracts\StorageInterface;

/**
 * Ensures MySQL stores exist and seeds first-run data from database/seeds/*.json.
 * Admin changes live only in MySQL — git pulls do not overwrite them.
 */
final class StorageBootstrap
{
    public static function ensureInitialized(StorageInterface $db, array $config): void
    {
        $driver = strtolower((string) ($config['storage']['driver'] ?? 'json'));
        if ($driver !== 'mysql') {
            return;
        }

        if (!DatabaseInstaller::ensureSchema($config)) {
            return;
        }

        $seedsDir = dirname(__DIR__, 2) . '/database/seeds';

        foreach (StorageKeys::primaryStores() as $store) {
            if ($store === StorageKeys::ADMIN) {
                continue;
            }
            if ($db->exists($store)) {
                continue;
            }

            $seedFile = $seedsDir . '/' . $store . '.json';
            if (!is_file($seedFile)) {
                continue;
            }

            $raw = file_get_contents($seedFile);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($data)) {
                continue;
            }

            $db->write($store, $data);
        }

        AdminAuth::createDefaultAdmin($db);
    }
}
