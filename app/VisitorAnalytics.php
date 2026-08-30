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
    private string $siteHost;

    public function __construct(array $config, Settings $settings)
    {
        $this->repo = new VisitorAnalyticsRepository(DatabaseConnection::get());
        $this->configEnabled = (bool) ($config['analytics']['enabled'] ?? true);
        $this->settings = $settings;
        $host = (string) (parse_url((string) ($config['app']['url'] ?? ''), PHP_URL_HOST) ?? '');
        $this->siteHost = strtolower(preg_replace('/:\d+$/', '', $host));
    }

    public function isEnabled(): bool
    {
        return $this->configEnabled && (bool) $this->settings->get('analytics_enabled', true);
    }

    public function recordPageView(string $path, string $pageTitle = ''): ?int
    {
        if (!$this->isEnabled() || !self::isTrackablePath($path)) {
            return null;
        }

        $ip = Security::clientIp();
        if ($ip === '' || !Security::isValidIp($ip)) {
            return null;
        }

        $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($ua !== '' && preg_match('/bot|crawl|spider|slurp|facebookexternalhit|bingpreview|HeadlessChrome/i', $ua)) {
            return null;
        }

        $parsed = UserAgentParser::parse($ua);
        $geo = GeoLookup::fromRequest($ip);
        $referrer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        $refInfo = VisitorAnalyticsDisplay::referrerInfo($referrer, $this->siteHost);
        $pageUrl = $this->currentPageUrl($path);
        $sessionKey = $this->sessionKey();

        if ($pageTitle === '') {
            $pageTitle = VisitorAnalyticsDisplay::pageLabel($path);
        }

        try {
            return $this->repo->insert([
                'session_key' => $sessionKey,
                'ip_address' => mb_substr($ip, 0, 45),
                'country_code' => $geo['code'],
                'country_name' => $geo['name'],
                'page_url' => mb_substr($pageUrl, 0, 2048),
                'page_path' => mb_substr($path, 0, 512),
                'page_title' => mb_substr($pageTitle, 0, 255),
                'referrer_url' => mb_substr($referrer, 0, 2048),
                'referrer_source' => mb_substr($refInfo['source'], 0, 128),
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

    public function clearLegacyHashed(): int
    {
        return $this->repo->clearLegacyHashed();
    }

    public function clearAll(): int
    {
        return $this->repo->clearAll();
    }

    public static function clientIp(): string
    {
        return Security::clientIp();
    }

    public static function shouldTrackPath(string $path, string $method): bool
    {
        return strtoupper($method) === 'GET' && self::isTrackablePath($path);
    }

    public static function isTrackablePath(string $path): bool
    {
        $path = '/' . trim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        if ($path === '/process'
            || str_starts_with($path, '/download/')
            || str_starts_with($path, '/api/')
            || str_starts_with($path, '/assets/')
            || str_starts_with($path, '/admin')
            || str_starts_with($path, '/open')
            || $path === '/x/r'
            || $path === '/assets/c/w'
            || $path === '/assets/c/d'
            || $path === '/favicon.ico'
            || $path === '/robots.txt'
            || $path === '/sitemap.xml'
        ) {
            return false;
        }

        if (preg_match('/\.(ico|png|jpe?g|gif|webp|svg|css|js|woff2?|ttf|map|txt|xml)$/i', $path)) {
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
