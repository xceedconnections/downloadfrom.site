<?php

declare(strict_types=1);

namespace App;

/** Human-readable labels for visitor analytics in admin. */
final class VisitorAnalyticsDisplay
{
    /** @param array<int, array<string, mixed>> $videoPlatforms @param array<int, array<string, mixed>> $audioPlatforms */
    public static function pageLabel(string $path, array $videoPlatforms = [], array $audioPlatforms = []): string
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            return 'Homepage';
        }

        $slug = ltrim($path, '/');

        if ($slug === ServiceConfig::PAGE_FAQ) {
            return 'FAQ';
        }
        if ($slug === ServiceConfig::PAGE_VIDEO) {
            return 'Video Converter';
        }
        if ($slug === ServiceConfig::PAGE_AUDIO) {
            return 'Audio Converter';
        }

        static $staticPages = [
            'privacy' => 'Privacy Policy',
            'terms' => 'Terms of Service',
            'dmca' => 'DMCA Policy',
            'contact' => 'Contact',
        ];
        if (isset($staticPages[$slug])) {
            return $staticPages[$slug];
        }

        if (str_starts_with($slug, 'result/')) {
            return 'Download Result';
        }

        if (str_starts_with($slug, 'blog/')) {
            $articleSlug = substr($slug, 5);
            $titles = [
                'how-to-save-tiktok-videos' => 'Blog: TikTok Guide',
                'how-to-save-youtube-videos' => 'Blog: YouTube Guide',
                'how-to-save-vimeo-videos' => 'Blog: Vimeo Guide',
            ];

            return $titles[$articleSlug] ?? 'Blog Article';
        }

        $platform = ServiceConfig::findPlatformBySlug($slug, $videoPlatforms, $audioPlatforms);
        if ($platform !== null) {
            $name = (string) ($platform['name'] ?? ucfirst($slug));

            return $name . ' Downloader';
        }

        return $slug !== '' ? ucwords(str_replace('-', ' ', $slug)) : 'Unknown Page';
    }

    /** @return array{source: string, detail: string, external: bool} */
    public static function referrerInfo(string $referrerUrl, string $siteHost): array
    {
        $referrerUrl = trim($referrerUrl);
        if ($referrerUrl === '') {
            return ['source' => 'Direct', 'detail' => 'Typed URL or bookmark', 'external' => false];
        }

        $host = strtolower((string) (parse_url($referrerUrl, PHP_URL_HOST) ?? ''));
        $siteHost = strtolower(preg_replace('/:\d+$/', '', $siteHost));

        if ($host === '' || $host === $siteHost || str_ends_with($host, '.' . $siteHost)) {
            if (str_contains($referrerUrl, '/admin')) {
                return ['source' => 'Internal', 'detail' => 'Admin panel', 'external' => false];
            }

            return ['source' => 'Internal', 'detail' => 'Same website', 'external' => false];
        }

        $searchEngines = [
            'google.' => 'Google',
            'bing.' => 'Bing',
            'yahoo.' => 'Yahoo',
            'duckduckgo.' => 'DuckDuckGo',
            'yandex.' => 'Yandex',
            'baidu.' => 'Baidu',
            'ecosia.' => 'Ecosia',
        ];

        foreach ($searchEngines as $needle => $label) {
            if (str_contains($host, $needle)) {
                return ['source' => $label . ' Search', 'detail' => $referrerUrl, 'external' => true];
            }
        }

        $social = [
            'facebook.' => 'Facebook',
            'fb.' => 'Facebook',
            'instagram.' => 'Instagram',
            'twitter.' => 'Twitter / X',
            'x.com' => 'Twitter / X',
            't.co' => 'Twitter / X',
            'tiktok.' => 'TikTok',
            'reddit.' => 'Reddit',
            'linkedin.' => 'LinkedIn',
            'pinterest.' => 'Pinterest',
            'youtube.' => 'YouTube',
        ];

        foreach ($social as $needle => $label) {
            if (str_contains($host, $needle)) {
                return ['source' => $label, 'detail' => $referrerUrl, 'external' => true];
            }
        }

        return ['source' => 'External Link', 'detail' => $referrerUrl, 'external' => true];
    }

    public static function countryFlag(string $code): string
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z]{2}$/', $code)) {
            return '';
        }

        $a = mb_chr(127462 + ord($code[0]) - ord('A'));
        $b = mb_chr(127462 + ord($code[1]) - ord('A'));

        return $a . $b;
    }

    public static function isLegacyHashedIp(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || Security::isValidIp($value)) {
            return false;
        }

        return (bool) preg_match('/^[a-f0-9]{32,64}$/i', $value);
    }

    public static function formatIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return '—';
        }
        if (self::isLegacyHashedIp($ip)) {
            return '(hashed — clear old data)';
        }

        return $ip;
    }

    /** @return string[] */
    public static function referrerBadgeClass(string $source): string
    {
        return match (true) {
            $source === 'Direct' => 'va-badge va-badge-direct',
            $source === 'Internal' => 'va-badge va-badge-internal',
            str_contains($source, 'Search') => 'va-badge va-badge-search',
            str_contains($source, 'External') => 'va-badge va-badge-external',
            default => 'va-badge va-badge-social',
        };
    }
}
