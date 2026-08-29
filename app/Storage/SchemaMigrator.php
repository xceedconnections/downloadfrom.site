<?php

declare(strict_types=1);

namespace App\Storage;

use App\AdminAuth;
use App\Repositories\AdminRepository;
use App\Repositories\AdsRepository;
use App\Repositories\AnalyticsRepository;
use App\Repositories\DownloadSessionRepository;
use App\Repositories\FaqRepository;
use App\Repositories\RateLimitRepository;
use App\Repositories\SettingsRepository;
use App\ServiceConfig;
use PDO;

/**
 * Creates relational tables, seeds first-run data, and migrates legacy app_storage JSON.
 */
final class SchemaMigrator
{
    private const MIGRATION_KEY = 'relational_v1';

    /** @param array<string, mixed> $config */
    public static function runIfNeeded(PDO $pdo, array $config, MysqlStorage $legacyStorage): void
    {
        if (self::isApplied($pdo)) {
            return;
        }

        $settingsRepo = new SettingsRepository($pdo);
        $adsRepo = new AdsRepository($pdo);
        $faqRepo = new FaqRepository($pdo);
        $adminRepo = new AdminRepository($pdo);
        $analyticsRepo = new AnalyticsRepository($pdo);
        $rateRepo = new RateLimitRepository($pdo);
        $sessionsRepo = new DownloadSessionRepository($pdo);

        $settingsDefaults = [
            'site_name' => 'VideoLink',
            'site_url' => 'http://localhost/downloadfrom',
            'analytics_enabled' => true,
            'maintenance_mode' => false,
            'admin_email' => 'admin@example.com',
            'footer_text' => 'Free online video URL tool for supported platforms. Retrieve public metadata and permitted viewing options.',
            'logo_path' => '',
            'custom_codes' => ['head' => '', 'body_end' => ''],
            'services' => ServiceConfig::defaultServices(),
            'providers' => [],
            'audio_providers' => [],
        ];

        $migratedFromLegacy = false;

        if ($legacyStorage->exists(StorageKeys::SETTINGS)) {
            $settingsRepo->importFromLegacy(
                $legacyStorage->read(StorageKeys::SETTINGS, []),
                $settingsDefaults
            );
            $migratedFromLegacy = true;
        }

        if ($legacyStorage->exists(StorageKeys::ADS)) {
            $adsRepo->importFromLegacy(
                $legacyStorage->read(StorageKeys::ADS, []),
                ['enabled' => false, 'download_modal_countdown' => 5, 'placement_map' => [], 'ads' => []]
            );
            $migratedFromLegacy = true;
        }

        if ($legacyStorage->exists(StorageKeys::FAQ)) {
            $faqRepo->importFromLegacy($legacyStorage->read(StorageKeys::FAQ, []));
            $migratedFromLegacy = true;
        }

        if ($legacyStorage->exists(StorageKeys::ADMIN)) {
            $adminRepo->importFromLegacy($legacyStorage->read(StorageKeys::ADMIN, []));
            $migratedFromLegacy = true;
        }

        if ($legacyStorage->exists(StorageKeys::ANALYTICS)) {
            $analyticsRepo->importFromLegacy($legacyStorage->read(StorageKeys::ANALYTICS, []));
            $migratedFromLegacy = true;
        }

        if ($legacyStorage->exists(StorageKeys::RATE_LIMITS)) {
            $rateRepo->importFromLegacy($legacyStorage->read(StorageKeys::RATE_LIMITS, []));
            $migratedFromLegacy = true;
        }

        foreach (DatabaseInstaller::listStores($config) as $storeKey) {
            if (!str_starts_with($storeKey, 'results/')) {
                continue;
            }
            $token = substr($storeKey, strlen('results/'));
            if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
                continue;
            }
            $wrapper = $legacyStorage->read($storeKey, []);
            if (!is_array($wrapper) || !isset($wrapper['data'])) {
                continue;
            }
            $created = (int) ($wrapper['created'] ?? time());
            $expires = (int) ($wrapper['expires'] ?? ($created + 3600));
            $ttl = max(60, $expires - $created);
            $sessionsRepo->store($token, $wrapper['data'], $ttl);
            $migratedFromLegacy = true;
        }

        if (!$migratedFromLegacy) {
            self::seedFromFiles($pdo, $settingsDefaults);
        }

        if ($adminRepo->isEmpty()) {
            AdminAuth::createDefaultAdmin($legacyStorage, $adminRepo);
        }

        self::markApplied($pdo);
    }

    /** @param array<string, mixed> $settingsDefaults */
    private static function seedFromFiles(PDO $pdo, array $settingsDefaults): void
    {
        $seedsDir = dirname(__DIR__, 2) . '/database/seeds';
        $settingsRepo = new SettingsRepository($pdo);
        $adsRepo = new AdsRepository($pdo);
        $faqRepo = new FaqRepository($pdo);

        if ($settingsRepo->isEmpty()) {
            $file = $seedsDir . '/settings.json';
            if (is_file($file)) {
                $raw = file_get_contents($file);
                $data = is_string($raw) ? json_decode($raw, true) : null;
                if (is_array($data)) {
                    $settingsRepo->importFromLegacy($data, $settingsDefaults);
                }
            }
        }

        if ($adsRepo->isEmpty()) {
            $file = $seedsDir . '/ads.json';
            if (is_file($file)) {
                $raw = file_get_contents($file);
                $data = is_string($raw) ? json_decode($raw, true) : null;
                if (is_array($data)) {
                    $adsRepo->importFromLegacy($data, [
                        'enabled' => false,
                        'download_modal_countdown' => 5,
                        'placement_map' => [],
                        'ads' => [],
                    ]);
                }
            }
        }

        if ($faqRepo->isEmpty()) {
            $file = $seedsDir . '/faq.json';
            if (is_file($file)) {
                $raw = file_get_contents($file);
                $data = is_string($raw) ? json_decode($raw, true) : null;
                if (is_array($data)) {
                    $faqRepo->importFromLegacy($data);
                }
            }
        }
    }

    private static function isApplied(PDO $pdo): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration = ? LIMIT 1');
        $stmt->execute([self::MIGRATION_KEY]);
        return (bool) $stmt->fetchColumn();
    }

    private static function markApplied(PDO $pdo): void
    {
        $stmt = $pdo->prepare('INSERT IGNORE INTO schema_migrations (migration) VALUES (?)');
        $stmt->execute([self::MIGRATION_KEY]);
    }
}
