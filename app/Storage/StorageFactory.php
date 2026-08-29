<?php

declare(strict_types=1);

namespace App\Storage;

use App\Contracts\StorageInterface;
use App\JsonDatabase;
use RuntimeException;

final class StorageFactory
{
    public static function create(array $config): StorageInterface
    {
        $driver = strtolower((string) ($config['storage']['driver'] ?? 'json'));

        return match ($driver) {
            'json', 'file' => new JsonDatabase($config),
            'mysql' => new MysqlStorage($config),
            default => throw new RuntimeException('Unsupported storage driver: ' . $driver),
        };
    }
}
