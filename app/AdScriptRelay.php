<?php

declare(strict_types=1);

namespace App;

/**
 * Rewrites third-party ad script URLs through a same-origin relay so domain blocklists miss them.
 */
final class AdScriptRelay
{
    public static function relayPath(): string
    {
        return '/assets/c/w';
    }

    public static function relayUrl(string $remoteUrl, string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . self::relayPath() . '?u=' . self::encode($remoteUrl);
    }

    public static function encode(string $url): string
    {
        return rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
    }

    public static function decode(string $token): ?string
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $pad = strlen($token) % 4;
        if ($pad > 0) {
            $token .= str_repeat('=', 4 - $pad);
        }

        $url = base64_decode(strtr($token, '-_', '+/'), true);
        if (!is_string($url) || !preg_match('#^https://#i', $url)) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        if (Security::isPrivateIp($host)) {
            return null;
        }

        return $url;
    }

    public static function fetch(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        if (Security::resolveAndValidateHost($host, [$host]) === null) {
            return null;
        }

        $http = new HttpClient([$host]);
        $response = $http->get($url, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept: application/javascript,text/javascript,*/*;q=0.8',
            'Referer: https://' . $host . '/',
        ]);

        if (!$response['success'] || !is_string($response['body'] ?? null) || $response['body'] === '') {
            return null;
        }

        return $response['body'];
    }

    public static function normalizeRemoteUrl(string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        if (!preg_match('#^https://#i', $url)) {
            return null;
        }

        return $url;
    }

    public static function rewriteMarkup(string $html, string $baseUrl, bool $relayEnabled = true): string
    {
        if ($html === '' || !$relayEnabled) {
            return $html;
        }

        $html = preg_replace_callback(
            '/(<script\b[^>]*\ssrc=(["\']))([^"\']+)\2/i',
            static function (array $m) use ($baseUrl): string {
                $src = self::normalizeRemoteUrl($m[3]);
                if ($src === null || !self::shouldRelay($src, $baseUrl)) {
                    return $m[0];
                }

                return $m[1] . self::relayUrl($src, $baseUrl) . $m[2];
            },
            $html
        ) ?? $html;

        return preg_replace_callback(
            "/\\.src\\s*=\\s*(['\"])([^'\"]+)\\1/i",
            static function (array $m) use ($baseUrl): string {
                $src = self::normalizeRemoteUrl($m[2]);
                if ($src === null || !self::shouldRelay($src, $baseUrl)) {
                    return $m[0];
                }

                return '.src=' . $m[1] . self::relayUrl($src, $baseUrl) . $m[1];
            },
            $html
        ) ?? $html;
    }

    private static function shouldRelay(string $src, string $baseUrl): bool
    {
        if ($src === '') {
            return false;
        }

        if (str_starts_with($src, rtrim($baseUrl, '/') . self::relayPath())) {
            return false;
        }

        if (str_starts_with($src, '/')) {
            return false;
        }

        return preg_match('#^https://#i', $src) === 1;
    }
}
