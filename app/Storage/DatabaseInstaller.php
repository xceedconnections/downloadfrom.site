<?php

declare(strict_types=1);

namespace App\Storage;

use App\Logger;
use App\Repositories\PageSeoRepository;
use PDO;
use PDOException;
use Throwable;

/**
 * Creates all relational tables automatically when missing.
 */
final class DatabaseInstaller
{
    public static function ensureSchema(array $config): bool
    {
        $driver = strtolower((string) ($config['storage']['driver'] ?? 'json'));
        if ($driver !== 'mysql') {
            return true;
        }

        try {
            $pdo = self::connect($config);
            $schemaFile = dirname(__DIR__, 2) . '/database/schema.sql';
            if (!is_file($schemaFile)) {
                Logger::error('database/schema.sql not found');
                return false;
            }

            $sql = (string) file_get_contents($schemaFile);
            foreach (self::splitStatements($sql) as $statement) {
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }

            self::ensureColumn($pdo, 'ads', 'popup_display', "VARCHAR(16) NOT NULL DEFAULT 'modal' AFTER popup_closable");
            self::ensureColumn($pdo, 'ads', 'popup_content_mode', "VARCHAR(16) NOT NULL DEFAULT 'html' AFTER popup_display");
            self::ensureColumn($pdo, 'ads', 'impression_count', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER popup_content_mode');
            self::ensureColumn($pdo, 'ad_settings', 'download_opener_mode', "VARCHAR(16) NOT NULL DEFAULT 'random' AFTER download_modal_countdown");
            self::ensureColumn($pdo, 'ad_settings', 'download_opener_count', 'TINYINT NOT NULL DEFAULT 1 AFTER download_opener_mode');
            self::ensureColumn($pdo, 'ad_settings', 'download_opener_containers', 'MEDIUMTEXT NULL AFTER download_opener_count');
            self::ensureColumn($pdo, 'visitor_events', 'page_title', "VARCHAR(255) NOT NULL DEFAULT '' AFTER page_path");
            self::ensureColumn($pdo, 'visitor_events', 'referrer_source', "VARCHAR(128) NOT NULL DEFAULT '' AFTER referrer_url");
            self::ensureZoneAssignmentsMultiAd($pdo);
            self::seedPageSeoIfEmpty($pdo);
            self::upgradeProviderSeoDefaults($pdo);

            return true;
        } catch (Throwable $e) {
            Logger::error('MySQL schema install failed: ' . $e->getMessage());
            return false;
        }
    }

    /** @return list<string> */
    private static function splitStatements(string $sql): array
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $buffer = '';
        $statements = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $buffer .= $line . "\n";
            if (str_ends_with(rtrim($line), ';')) {
                $statements[] = trim($buffer);
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        if ((int) ($stmt->fetchColumn() ?: 0) > 0) {
            return;
        }

        $pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN `' . str_replace('`', '``', $column) . '` ' . $definition);
    }

    private static function seedPageSeoIfEmpty(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute(['page_seo']);
        if ((int) ($stmt->fetchColumn() ?: 0) === 0) {
            return;
        }

        (new PageSeoRepository($pdo))->seedDefaultsIfEmpty();
    }

    private static function upgradeProviderSeoDefaults(PDO $pdo): void
    {
        $migration = 'seo_defaults_v1';
        $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration = ? LIMIT 1');
        $check->execute([$migration]);
        if ((bool) $check->fetchColumn()) {
            return;
        }

        $videoSeo = [
            'title' => 'YouTube Video Downloader – Free MP4 HD 1080p Online',
            'h1' => 'YouTube Video Downloader',
            'meta_description' => 'Download YouTube videos free in 1080p, 720p, 480p MP4. Paste any YouTube or Shorts URL — fast, no signup, works on all devices.',
            'description' => 'Download YouTube videos in HD MP4 quality. Paste any public YouTube, youtu.be, or Shorts link and choose your preferred quality.',
            'keywords' => 'youtube video downloader, download youtube video, youtube to mp4, youtube mp4 downloader, youtube hd download, youtube shorts downloader',
        ];

        $audioSeo = [
            'title' => 'YouTube to MP3 Converter – Free YouTube MP3 Downloader',
            'h1' => 'YouTube to MP3 Converter',
            'meta_description' => 'Convert YouTube to MP3 free. Download YouTube audio in 128kbps, 192kbps or 320kbps — paste link, choose quality, save instantly.',
            'description' => 'Download MP3 audio from YouTube videos. Paste any public YouTube, youtu.be, or Shorts link and pick your audio quality.',
            'keywords' => 'youtube to mp3, youtube mp3 converter, youtube to mp3 downloader, convert youtube to mp3, youtube mp3 download free',
        ];

        $legacyVideoTitles = [
            'YouTube Video Link Tool – Get YouTube Video Information',
            'YouTube Video Downloader – Free MP4 HD 1080p Online',
        ];

        $stmt = $pdo->prepare(
            'UPDATE video_providers SET title = ?, h1 = ?, meta_description = ?, description = ?, keywords = ?
             WHERE provider_id = ? AND (title = ? OR title LIKE ? OR title = ?)'
        );
        $stmt->execute([
            $videoSeo['title'],
            $videoSeo['h1'],
            $videoSeo['meta_description'],
            $videoSeo['description'],
            $videoSeo['keywords'],
            'youtube',
            $legacyVideoTitles[0],
            '%Video Link Tool%',
            $legacyVideoTitles[1],
        ]);

        $stmt = $pdo->prepare(
            'UPDATE audio_providers SET title = ?, h1 = ?, meta_description = ?, description = ?, keywords = ?
             WHERE provider_id = ? AND (title LIKE ? OR title LIKE ? OR h1 LIKE ?)'
        );
        $stmt->execute([
            $audioSeo['title'],
            $audioSeo['h1'],
            $audioSeo['meta_description'],
            $audioSeo['description'],
            $audioSeo['keywords'],
            'youtube',
            '%MP3 Downloader%',
            '%YouTube to MP3%',
            '%MP3%',
        ]);

        $insert = $pdo->prepare('INSERT IGNORE INTO schema_migrations (migration) VALUES (?)');
        $insert->execute([$migration]);
    }

    private static function ensureZoneAssignmentsMultiAd(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute(['ad_zone_assignments']);
        if ((int) ($stmt->fetchColumn() ?: 0) === 0) {
            return;
        }

        self::ensureColumn($pdo, 'ad_zone_assignments', 'sort_order', 'INT NOT NULL DEFAULT 0 AFTER ad_id');

        $pkStmt = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'ad_zone_assignments'
               AND CONSTRAINT_TYPE = 'PRIMARY KEY'"
        );
        if ((int) ($pkStmt ? $pkStmt->fetchColumn() : 0) === 0) {
            return;
        }

        $colStmt = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'ad_zone_assignments'
               AND CONSTRAINT_NAME = 'PRIMARY'
             ORDER BY ORDINAL_POSITION"
        );
        $pkCols = $colStmt ? array_map('strval', $colStmt->fetchAll(PDO::FETCH_COLUMN)) : [];
        if ($pkCols === ['placement', 'ad_id']) {
            return;
        }

        if ($pkCols === ['placement']) {
            $pdo->exec('ALTER TABLE ad_zone_assignments DROP PRIMARY KEY, ADD PRIMARY KEY (placement, ad_id)');
        }
    }

    public static function connect(array $config): PDO
    {
        $mysql = $config['storage']['mysql'] ?? [];
        $host = (string) ($mysql['host'] ?? '127.0.0.1');
        $port = (int) ($mysql['port'] ?? 3306);
        $database = (string) ($mysql['database'] ?? '');
        $username = (string) ($mysql['username'] ?? '');
        $password = (string) ($mysql['password'] ?? '');

        if ($database === '') {
            throw new PDOException('storage.mysql.database is required when storage.driver is mysql');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /** @return array<string, int> */
    public static function tableCounts(array $config): array
    {
        $tables = [
            'site_settings',
            'video_providers',
            'audio_providers',
            'services',
            'ads',
            'page_seo',
            'faq_items',
            'admin_users',
            'analytics_daily',
            'visitor_events',
            'rate_limit_events',
            'download_sessions',
        ];

        try {
            $pdo = self::connect($config);
            $counts = [];
            foreach ($tables as $table) {
                $stmt = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`');
                $counts[$table] = (int) ($stmt ? $stmt->fetchColumn() : 0);
            }

            return $counts;
        } catch (PDOException $e) {
            return [];
        }
    }

    /** @return list<string> */
    public static function listStores(array $config): array
    {
        try {
            $pdo = self::connect($config);
            $stmt = $pdo->query('SELECT store_key FROM app_storage ORDER BY store_key');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

            return array_values(array_filter(array_map('strval', $rows)));
        } catch (PDOException $e) {
            return [];
        }
    }
}
