<?php

declare(strict_types=1);

namespace App\Storage;

use App\Logger;
use PDO;
use PDOException;
use Throwable;

/**
 * Creates app_storage table automatically when missing (no manual schema import required).
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
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS app_storage (
                    store_key VARCHAR(191) NOT NULL,
                    payload JSON NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (store_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            return true;
        } catch (Throwable $e) {
            Logger::error('MySQL schema install failed: ' . $e->getMessage());
            return false;
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
