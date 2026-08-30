<?php

declare(strict_types=1);

/**
 * Application configuration.
 * Copy config.local.php.example to config.local.php for environment overrides.
 */

$config = [
    'app' => [
        'name' => 'VideoLink',
        'tagline' => 'Get Video Links From Supported Platforms',
        'url' => 'auto',
        'env' => 'production',
        'debug' => false,
        'timezone' => 'UTC',
    ],

    'security' => [
        'csrf_token_name' => '_csrf_token',
        'session_name' => 'videolink_session',
        'max_url_length' => 2048,
        'max_post_size' => 8192,
        'result_token_ttl' => 3600,
    ],

    'rate_limit' => [
        'enabled' => true,
        'requests_per_minute' => 10,
        'requests_per_day' => 100,
        'admin_login_attempts' => 5,
        'admin_login_window' => 900,
    ],

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'path' => dirname(__DIR__) . '/storage/cache',
    ],

    'storage' => [
        // MySQL is required — all admin data lives in app_storage (see database/schema.sql)
        'driver' => 'mysql',
        'path' => dirname(__DIR__) . '/storage/data',
        'logs' => dirname(__DIR__) . '/storage/logs',
        'mysql' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'downloadfrom.site',
            'username' => 'downloadfrom.site',
            'password' => '',
        ],
    ],

    'analytics' => [
        'enabled' => true,
        'store_ip_hash' => true,
    ],

    'api_keys' => [
        'youtube' => '',
        'vimeo' => '',
        'dailymotion' => '',
    ],

    'ytdlp' => [
        'path' => dirname(__DIR__) . '/bin/yt-dlp.exe',
        'enabled' => true,
    ],

    'download' => [
        // false = direct CDN links (no server bandwidth). true = stream via /download/{token}/{index}
        'proxy_enabled' => false,
    ],

    'headers' => [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
    ],

    // Permissive CSP so any ad network (Monetag, AdSense, PropellerAds, etc.) can load scripts, iframes, and beacons.
    'csp' => "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: http: blob:; "
        . "style-src 'self' 'unsafe-inline' https:; "
        . "img-src 'self' data: blob: https: http:; "
        . "font-src 'self' data: https:; "
        . "connect-src 'self' https: http: wss:; "
        . "frame-src 'self' https: http:; "
        . "media-src 'self' https: http: blob:; "
        . "child-src 'self' https: http: blob:; "
        . "frame-ancestors 'self'; "
        . "base-uri 'self'; "
        . "form-action 'self'",
];

$localConfig = dirname(__DIR__) . '/config/config.local.php';
if (is_file($localConfig)) {
    $local = require $localConfig;
    $config = array_replace_recursive($config, $local);
}

return $config;
