<?php

declare(strict_types=1);

namespace App\Storage;

use App\AdminAuth;
use App\Contracts\StorageInterface;
use App\JsonDatabase;

/**
 * Ensures MySQL stores exist, seeds first-run data, and imports legacy JSON files once.
 */
final class StorageBootstrap
{
    public static function ensureInitialized(StorageInterface $db, array $config): void
    {
        if (strtolower((string) ($config['storage']['driver'] ?? '')) !== 'mysql') {
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

    /** Import storage/data/*.json into MySQL when stores are still empty (one-time legacy migration). */
    public static function migrateLegacyJsonIfNeeded(StorageInterface $db, array $config): void
    {
        if (strtolower((string) ($config['storage']['driver'] ?? '')) !== 'mysql') {
            return;
        }

        $jsonPath = rtrim((string) ($config['storage']['path'] ?? ''), '/\\');
        if ($jsonPath === '' || !is_dir($jsonPath)) {
            return;
        }

        $jsonConfig = $config;
        $jsonConfig['storage']['driver'] = 'json';
        $json = new JsonDatabase($jsonConfig);

        foreach (StorageKeys::primaryStores() as $store) {
            if ($db->exists($store) || !$json->exists($store)) {
                continue;
            }

            $data = $json->read($store);
            if (is_array($data) && $data !== []) {
                $db->write($store, $data);
            }
        }

        $resultsDir = $jsonPath . '/results';
        if (!is_dir($resultsDir)) {
            return;
        }

        foreach (glob($resultsDir . '/*.json') ?: [] as $file) {
            $token = basename($file, '.json');
            if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
                continue;
            }
            $store = StorageKeys::result($token);
            if ($db->exists($store)) {
                continue;
            }
            $raw = file_get_contents($file);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($data)) {
                $db->write($store, $data);
            }
        }
    }
}
