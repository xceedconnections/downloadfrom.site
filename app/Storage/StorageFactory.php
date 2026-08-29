<?php

declare(strict_types=1);

namespace App\Storage;

use App\Contracts\StorageInterface;
use App\JsonDatabase;
use RuntimeException;

/**
 * Creates the application storage backend. Production uses MySQL only.
 */
final class StorageFactory
{
    public static function create(array $config): StorageInterface
    {
        $driver = strtolower((string) ($config['storage']['driver'] ?? 'mysql'));

        if ($driver !== 'mysql') {
            throw new RuntimeException(
                'This site requires MySQL storage. Set storage.driver to "mysql" in config/config.local.php'
            );
        }

        $password = (string) ($config['storage']['mysql']['password'] ?? '');
        $localConfig = dirname(__DIR__, 2) . '/config/config.local.php';
        if ($password === '' && !is_file($localConfig)) {
            throw new RuntimeException(
                'MySQL password missing. Copy config/config.local.php.example to config/config.local.php and set storage.mysql.password'
            );
        }

        if (!DatabaseInstaller::ensureSchema($config)) {
            throw new RuntimeException(
                'Could not create MySQL table app_storage. Check database credentials in config/config.local.php'
            );
        }

        $db = new MysqlStorage($config);
        StorageBootstrap::ensureInitialized($db, $config);
        StorageBootstrap::migrateLegacyJsonIfNeeded($db, $config);

        return $db;
    }
}
