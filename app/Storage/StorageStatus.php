<?php

declare(strict_types=1);

namespace App\Storage;

use App\Contracts\StorageInterface;

/** Reports MySQL storage health for admin dashboard. */
final class StorageStatus
{
    /** @return array{driver: string, database: string, host: string, stores: array<string, bool>, row_count: int} */
    public static function summary(array $config, StorageInterface $db): array
    {
        $mysql = $config['storage']['mysql'] ?? [];
        $stores = [];

        foreach (StorageKeys::primaryStores() as $key) {
            $stores[$key] = $db->exists($key);
        }

        return [
            'driver' => (string) ($config['storage']['driver'] ?? 'mysql'),
            'database' => (string) ($mysql['database'] ?? ''),
            'host' => (string) ($mysql['host'] ?? '127.0.0.1'),
            'stores' => $stores,
            'row_count' => count(DatabaseInstaller::listStores($config)),
        ];
    }

    /** Human labels for primary store keys. */
    public static function storeLabels(): array
    {
        return [
            StorageKeys::SETTINGS => 'Site settings, services, provider SEO',
            StorageKeys::ADS => 'Ads & placement map',
            StorageKeys::FAQ => 'FAQ content',
            StorageKeys::ADMIN => 'Admin login',
            StorageKeys::ANALYTICS => 'Analytics',
            StorageKeys::RATE_LIMITS => 'Rate limits',
        ];
    }
}
