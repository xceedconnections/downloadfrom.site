<?php

declare(strict_types=1);

namespace App\Storage;

use PDO;

/** Shared PDO connection for relational tables. */
final class DatabaseConnection
{
    private static ?PDO $pdo = null;

    /** @param array<string, mixed> $config */
    public static function configure(array $config): void
    {
        if (self::$pdo !== null) {
            return;
        }

        self::$pdo = DatabaseInstaller::connect($config);
    }

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException('DatabaseConnection not configured. Call configure() first.');
        }

        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
