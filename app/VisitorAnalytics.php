<?php

declare(strict_types=1);

namespace App;

use App\Repositories\VisitorAnalyticsRepository;
use App\Storage\DatabaseConnection;

/** Records public page visits and time-on-page in MySQL. */
final class VisitorAnalytics
{
    private VisitorAnalyticsRepository $repo;
    private bool $configEnabled;
    private Settings $settings;
    private bool $storeIpHash;

    public function __construct(array $config, Settings $settings)
    {
        $this->repo = new VisitorAnalyticsRepository(DatabaseConnection::get());
        $this->configEnabled = (bool) ($config['analytics']['enabled'] ?? true);
        $this->settings = $settings;
        $this->storeIpHash = (bool) ($config['analytics']['store_ip_hash'] ?? false);
    }

    public function isEnabled(): bool
    {
        return $this->configEnabled && (bool) $this->settings->get('analytics_enabled', true);
    }

    public function recordPageView(string $path): ?int
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $ip = self::clientIp();
        if ($ip === '') {
            return null;
        }

        $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($ua !== '' && preg_match('/bot|crawl|spider|slurp|facebookexternalhit|bingpreview|HeadlessChrome/i', $ua)) {
            return null;
        }

        $parsed = UserAgentParser::parse($ua);
        $geo = GeoLookup::fromRequest();
        $referrer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        $pageUrl = $this->currentPageUrl($path);
        $sessionKey = $this->sessionKey();

        $storedIp = $this->storeIpHash ? Security::hashIp($ip) : $ip;

        try {
            return $this->repo->insert([
                'session_key' => $sessionKey,
                'ip_address' => mb_substr($storedIp, 0, 45),
                'country_code' => $geo['code'],
                'country_name' => $geo['name'],
                'page_url' => mb_substr($pageUrl, 0, 2048),
                'page_path' => mb_substr($path, 0, 512),
                'referrer_url' => mb_substr($referrer, 0, 2048),
                'user_agent' => $ua,
                'browser' => $parsed['browser'],
                'os_name' => $parsed['os_name'],
                'device_type' => $parsed['device_type'],
                'visited_at' => time(),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function recordLeave(int $visitId, int $durationSeconds): bool
    {
        if (!$this->isEnabled() || $visitId <= 0) {
            return false;
        }

        try {
            return $this->repo->updateDuration($visitId, $durationSeconds, time());
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    public function getSummary(int $days = 7): array
    {
        $since = time() - max(1, $days) * 86400;

        return $this->repo->summarySince($since);
    }

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function listVisits(int $days, int $page, int $perPage): array
    {
        $since = time() - max(1, $days) * 86400;
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $total = $this->repo->countSince($since);

        return [
            'rows' => $this->repo->listSince($since, $perPage, $offset),
            'total' => $total,
        ];
    }

    public function clearAll(): int
    {
        return $this->repo->clearAll();
    }

    public static function clientIp(): string
    {
        $candidates = [];
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $raw = trim((string) ($_SERVER[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            foreach (explode(',', $raw) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $candidates[] = $part;
                }
            }
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
                return $ip;
            }
        }

        return '';
    }

    public static function shouldTrackPath(string $path, string $method): bool
    {
        if (strtoupper($method) !== 'GET') {
            return false;
        }

        if ($path === '/process'
            || str_starts_with($path, '/download/')
            || str_starts_with($path, '/api/')
            || str_starts_with($path, '/assets/')
            || $path === '/x/r'
            || $path === '/assets/c/w'
            || $path === '/assets/c/d'
        ) {
            return false;
        }

        return true;
    }

    private function sessionKey(): string
    {
        if (empty($_SESSION['_visitor_key'])) {
            $_SESSION['_visitor_key'] = Security::generateToken(16);
        }

        return (string) $_SESSION['_visitor_key'];
    }

    private function currentPageUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return $path;
        }

        $query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        $url = $scheme . '://' . $host . $path;

        return $query !== '' ? $url . '?' . $query : $url;
    }
}
