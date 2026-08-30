<?php

declare(strict_types=1);

namespace App\Storage;

use App\Contracts\StorageInterface;

/** Reports MySQL storage health for admin dashboard. */
final class StorageStatus
{
    /** @return array{driver: string, database: string, host: string, tables: array<string, int>, legacy_rows: int} */
    public static function summary(array $config, StorageInterface $db): array
    {
        $mysql = $config['storage']['mysql'] ?? [];

        return [
            'driver' => (string) ($config['storage']['driver'] ?? 'mysql'),
            'database' => (string) ($mysql['database'] ?? ''),
            'host' => (string) ($mysql['host'] ?? '127.0.0.1'),
            'tables' => DatabaseInstaller::tableCounts($config),
            'legacy_rows' => count(DatabaseInstaller::listStores($config)),
        ];
    }

    /** Human labels for relational tables. */
    public static function tableLabels(): array
    {
        return [
            'site_settings' => 'Site settings',
            'video_providers' => 'Video providers',
            'audio_providers' => 'Audio providers',
            'services' => 'Services',
            'ads' => 'Ads (one row per ad)',
            'faq_items' => 'FAQ items',
            'admin_users' => 'Admin users',
            'analytics_daily' => 'Analytics (daily)',
            'visitor_events' => 'Visitor analytics',
            'rate_limit_events' => 'Rate limit events',
            'download_sessions' => 'Download sessions',
        ];
    }
}
