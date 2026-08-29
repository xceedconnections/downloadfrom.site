<?php

declare(strict_types=1);

namespace App;

/**
 * Resolves the public site base URL from the current request (host + install path).
 * Works when accessed via localhost, LAN IP, or a custom domain in a subdirectory.
 */
class AppUrl
{
    /** @param array<string, mixed> $config */
    public static function resolve(array $config, ?string $appRoot = null): string
    {
        $configured = trim((string) ($config['app']['url'] ?? ''));

        if ($configured !== '' && strtolower($configured) !== 'auto') {
            return rtrim($configured, '/');
        }

        if (PHP_SAPI === 'cli') {
            return $configured !== '' && strtolower($configured) !== 'auto'
                ? rtrim($configured, '/')
                : 'http://localhost/downloadfrom';
        }

        return self::detectFromRequest($appRoot ?? dirname(__DIR__));
    }

    public static function detectFromRequest(string $appRoot): string
    {
        $scheme = self::requestScheme();
        $host = self::requestHost();
        $basePath = self::installPath($appRoot);

        return rtrim($scheme . '://' . $host . $basePath, '/');
    }

    /** @param array<string, mixed> $config */
    public static function basePath(array $config, ?string $appRoot = null): string
    {
        $url = self::resolve($config, $appRoot);
        $path = parse_url($url, PHP_URL_PATH);

        return ($path === null || $path === '' || $path === '/') ? '' : rtrim($path, '/');
    }

    /** @param array<string, mixed> $config */
    public static function apply(array &$config, ?string $appRoot = null): void
    {
        $config['app']['url'] = self::resolve($config, $appRoot);
    }

    private static function requestScheme(): string
    {
        $server = $_SERVER;

        if (!empty($server['HTTP_X_FORWARDED_PROTO'])) {
            return strtolower((string) $server['HTTP_X_FORWARDED_PROTO']) === 'https' ? 'https' : 'http';
        }

        if (!empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off') {
            return 'https';
        }

        if (isset($server['SERVER_PORT']) && (int) $server['SERVER_PORT'] === 443) {
            return 'https';
        }

        return 'http';
    }

    private static function requestHost(): string
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));

        return $host !== '' ? $host : 'localhost';
    }

    private static function installPath(string $appRoot): string
    {
        $docRoot = self::realPath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appReal = self::realPath($appRoot);

        if ($docRoot !== '' && $appReal !== '' && str_starts_with($appReal, $docRoot)) {
            $relative = substr($appReal, strlen($docRoot));
            $relative = str_replace('\\', '/', $relative);

            return $relative === '' ? '' : '/' . trim($relative, '/');
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptName === '') {
            return '';
        }

        $dir = dirname($scriptName);
        if (str_ends_with($dir, '/admin')) {
            $dir = dirname($dir);
        }

        if ($dir === '/' || $dir === '.' || $dir === '\\') {
            return '';
        }

        return rtrim($dir, '/');
    }

    private static function realPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $real = realpath($path);

        return $real !== false ? str_replace('\\', '/', $real) : '';
    }
}
