<?php

declare(strict_types=1);

namespace App\Storage;

use App\Logger;
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
            'faq_items',
            'admin_users',
            'analytics_daily',
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
