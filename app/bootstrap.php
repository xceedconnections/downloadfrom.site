<?php

declare(strict_types=1);

/**
 * Application bootstrap – autoloading, config, error handling.
 */

$config = require dirname(__DIR__) . '/config/config.php';

date_default_timezone_set($config['app']['timezone']);

if (!$config['app']['debug']) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($config): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    $logFile = $config['storage']['logs'] . '/error-' . date('Y-m-d') . '.log';
    $entry = sprintf("[%s] %s in %s:%d\n", date('c'), $message, basename($file), $line);
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    return true;
});

set_exception_handler(static function (Throwable $e) use ($config): void {
    $logFile = $config['storage']['logs'] . '/error-' . date('Y-m-d') . '.log';
    $entry = sprintf("[%s] Uncaught %s: %s in %s:%d\n", date('c'), get_class($e), $e->getMessage(), basename($e->getFile()), $e->getLine());
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

    http_response_code(500);
    if ($config['app']['debug']) {
        echo '<h1>Application Error</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    } else {
        require dirname(__DIR__) . '/templates/error.php';
    }
    exit;
});

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

\App\AppUrl::apply($config, dirname(__DIR__));

return $config;
