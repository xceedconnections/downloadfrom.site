<?php

declare(strict_types=1);

namespace App;

class Logger
{
    private static ?string $logDir = null;

    public static function init(array $config): void
    {
        self::$logDir = rtrim($config['storage']['logs'], '/\\');
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0750, true);
        }
    }

    public static function error(string $message): void
    {
        self::write('error', $message);
    }

    public static function info(string $message): void
    {
        self::write('info', $message);
    }

    public static function access(string $message): void
    {
        self::write('access', $message);
    }

    private static function write(string $type, string $message): void
    {
        if (self::$logDir === null) {
            return;
        }
        $file = self::$logDir . '/' . $type . '-' . date('Y-m-d') . '.log';
        $entry = sprintf("[%s] %s\n", date('c'), $message);
        @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
    }
}
