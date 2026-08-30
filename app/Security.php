<?php

declare(strict_types=1);

namespace App;

class Security
{
    private static array $privateRanges = [
        ['10.0.0.0', '10.255.255.255'],
        ['127.0.0.0', '127.255.255.255'],
        ['169.254.0.0', '169.254.255.255'],
        ['172.16.0.0', '172.31.255.255'],
        ['192.168.0.0', '192.168.255.255'],
        ['0.0.0.0', '0.255.255.255'],
    ];

    public static function initSession(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

        session_name($config['security']['session_name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    public static function setHeaders(array $config): void
    {
        foreach ($config['headers'] as $name => $value) {
            header("$name: $value");
        }
        if (!empty($config['csp'])) {
            header('Content-Security-Policy: ' . $config['csp']);
        }
    }

    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function validateCsrfToken(?string $token, array $config): bool
    {
        $expected = $_SESSION[$config['security']['csrf_token_name']] ?? $_SESSION['_csrf_token'] ?? '';
        return is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }

    public static function csrfField(array $config): string
    {
        $token = self::generateCsrfToken();
        $name = htmlspecialchars($config['security']['csrf_token_name'], ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . $name . '" value="' . $value . '">';
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Allow safe HTML from trusted admin content (FAQ answers, ad copy). */
    public static function sanitizeAdminHtml(string $html): string
    {
        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote><span><div>';
        $clean = strip_tags($html, $allowed);

        return preg_replace_callback(
            '/<a\s([^>]*)>(.*?)<\/a>/is',
            static function (array $m): string {
                if (!preg_match('/href=(["\'])(.*?)\1/i', $m[1], $href)) {
                    return $m[2];
                }
                $url = trim($href[2]);
                if ($url === '' || preg_match('#^\s*(javascript|data|vbscript):#i', $url)) {
                    return $m[2];
                }
                if (!preg_match('#^https?://#i', $url) && !str_starts_with($url, '/') && !str_starts_with($url, '#')) {
                    return $m[2];
                }

                return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" rel="noopener noreferrer">' . $m[2] . '</a>';
            },
            $clean
        ) ?? $clean;
    }

    public static function stripHtmlToPlaintext(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    public static function isPrivateIp(string $ip): bool
    {
        if ($ip === '::1' || str_starts_with($ip, 'fe80:') || str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')) {
            return true;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $long = ip2long($ip);
        if ($long === false) {
            return true;
        }

        foreach (self::$privateRanges as [$start, $end]) {
            if ($long >= ip2long($start) && $long <= ip2long($end)) {
                return true;
            }
        }

        return false;
    }

    public static function validateOutboundHost(string $host, array $allowedDomains): bool
    {
        $host = strtolower($host);
        $host = preg_replace('/:\d+$/', '', $host);

        foreach ($allowedDomains as $domain) {
            $domain = strtolower($domain);
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    public static function resolveAndValidateHost(string $host, array $allowedDomains): ?string
    {
        if (!self::validateOutboundHost($host, $allowedDomains)) {
            return null;
        }

        $ips = @dns_get_record($host, DNS_A + DNS_AAAA);
        if ($ips === false || $ips === []) {
            $resolved = @gethostbyname($host);
            if ($resolved === $host || self::isPrivateIp($resolved)) {
                return null;
            }
            return $resolved;
        }

        foreach ($ips as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip !== null && self::isPrivateIp($ip)) {
                return null;
            }
        }

        return $host;
    }

    public static function hashIp(string $ip): string
    {
        return hash('sha256', $ip . '|videolink_salt');
    }

    /** Real visitor IP — never hashed. Checks proxy headers used by Cloudflare, nginx, and aaPanel. */
    public static function clientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_TRUE_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_CLIENT_IP',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        $candidates = [];
        foreach ($headers as $key) {
            $raw = trim((string) ($_SERVER[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            foreach (explode(',', $raw) as $part) {
                $part = trim($part);
                if ($part !== '' && self::isValidIp($part)) {
                    $candidates[] = $part;
                }
            }
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !self::isPrivateIp($ip)) {
                return $ip;
            }
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && !self::isPrivateIp($ip)) {
                return $ip;
            }
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $ip;
            }
        }

        return '';
    }

    public static function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    }

    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}
