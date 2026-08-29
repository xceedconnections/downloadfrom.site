<?php

declare(strict_types=1);

namespace App;

/**
 * Merges admin settings with provider manifest data.
 */
class PlatformConfig
{
    private const SEO_FIELDS = ['title', 'h1', 'meta_description', 'description', 'keywords', 'slug'];

    /** @param array<string, array> $platforms */
    public static function mergePlatforms(array $platforms, Settings $settings, string $serviceType = 'video'): array
    {
        $merged = [];
        foreach ($platforms as $id => $platform) {
            if (!self::isEnabled($settings, $id, $serviceType)) {
                continue;
            }
            $merged[$id] = self::applyOverrides($platform, $settings, $id, $serviceType);
        }

        return $merged;
    }

    public static function applyOverrides(array $platform, Settings $settings, string $id, string $serviceType = 'video'): array
    {
        $ps = self::providerSettings($settings, $id, $serviceType);
        foreach (self::SEO_FIELDS as $field) {
            if (!empty($ps[$field])) {
                $platform[$field] = $ps[$field];
            }
        }
        $platform['proxy_enabled'] = self::isProxyEnabled($settings, $id, null, $serviceType);
        $platform['show_as_new'] = self::showAsNew($settings, $id, $serviceType);
        $platform['service_type'] = $serviceType;

        return $platform;
    }

    public static function showAsNew(Settings $settings, string $id, string $serviceType = 'video'): bool
    {
        $ps = self::providerSettings($settings, $id, $serviceType);
        return !empty($ps['show_as_new']);
    }

    /** @return array<string, mixed> */
    public static function providerSettings(Settings $settings, string $id, string $serviceType = 'video'): array
    {
        $root = $serviceType === 'audio' ? 'audio_providers' : 'providers';
        $ps = $settings->get($root . '.' . $id, []);
        return is_array($ps) ? $ps : [];
    }

    public static function isEnabled(Settings $settings, string $id, string $serviceType = 'video'): bool
    {
        $ps = self::providerSettings($settings, $id, $serviceType);
        return !isset($ps['enabled']) || $ps['enabled'] !== false;
    }

    public static function isProxyEnabled(Settings $settings, string $id, ?array $config = null, string $serviceType = 'video'): bool
    {
        $ps = self::providerSettings($settings, $id, $serviceType);
        if (array_key_exists('proxy_enabled', $ps)) {
            return (bool) $ps['proxy_enabled'];
        }

        return (bool) ($config['download']['proxy_enabled'] ?? false);
    }

    /** @return string[] */
    public static function blockedChannels(Settings $settings, string $id, string $serviceType = 'video'): array
    {
        $list = self::providerSettings($settings, $id, $serviceType)['blocked_channels'] ?? [];
        if (!is_array($list)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $list)));
    }

    public static function logoUrl(Settings $settings, string $baseUrl): ?string
    {
        $path = trim((string) $settings->get('logo_path', ''));
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    public static function iconKey(array $platform): string
    {
        $key = trim((string) ($platform['icon'] ?? $platform['key'] ?? $platform['id'] ?? ''));

        return preg_replace('/[^a-z0-9_-]/', '', strtolower($key)) ?: 'default';
    }

    public static function iconRelativePath(string $iconKey, string $type = 'platform'): ?string
    {
        $safe = preg_replace('/[^a-z0-9_-]/', '', strtolower($iconKey));
        if ($safe === '') {
            return null;
        }

        $subdir = $type === 'service' ? 'services' : 'platforms';
        $relative = "assets/icons/{$subdir}/{$safe}.svg";
        $absolute = dirname(__DIR__) . '/' . $relative;

        if (is_file($absolute)) {
            return $relative;
        }

        if ($type === 'platform' && $safe !== 'default') {
            return self::iconRelativePath('default', 'platform');
        }

        return null;
    }

    public static function iconUrl(array $platform, string $baseUrl): ?string
    {
        $relative = self::iconRelativePath(self::iconKey($platform), 'platform');
        if ($relative === null) {
            return null;
        }

        return rtrim($baseUrl, '/') . '/' . $relative;
    }

    public static function serviceIconUrl(string $serviceId, string $baseUrl): ?string
    {
        $key = $serviceId === ServiceConfig::SERVICE_AUDIO ? 'audio' : 'video';
        $relative = self::iconRelativePath($key, 'service');

        return $relative !== null ? rtrim($baseUrl, '/') . '/' . $relative : null;
    }

    /** @param array<string, array> $platforms @return string[] */
    public static function platformNames(array $platforms): array
    {
        $names = [];
        foreach ($platforms as $platform) {
            $name = trim((string) ($platform['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** @param array<string, array> $platforms */
    public static function formatNameList(array $platforms): string
    {
        $names = self::platformNames($platforms);
        $count = count($names);

        if ($count === 0) {
            return 'supported video platforms';
        }
        if ($count === 1) {
            return $names[0];
        }
        if ($count === 2) {
            return $names[0] . ' and ' . $names[1];
        }

        $last = array_pop($names);

        return implode(', ', $names) . ', and ' . $last;
    }

    /** @param array<string, array> $platforms */
    public static function supportedPlatformsSentence(array $platforms): string
    {
        return 'We support ' . self::formatNameList($platforms) . '.';
    }

    /** @param array<string, array> $platforms */
    public static function pasteUrlDescription(array $platforms): string
    {
        $list = self::formatNameList($platforms);

        return 'Paste a video URL from ' . $list . '. Retrieve public metadata, thumbnails, and permitted viewing options instantly.';
    }

    /** @param array<string, array> $platforms */
    public static function multiPlatformBlurb(array $platforms): string
    {
        return 'One tool for ' . self::formatNameList($platforms) . '.';
    }

    /** @param array<string, array> $platforms */
    public static function platformExamplesBlurb(array $platforms): string
    {
        $list = self::formatNameList($platforms);

        return 'Whether you have content from ' . $list . ', simply paste the URL and generate available options.';
    }

    /** @param array<string, array> $platforms */
    public static function keywordsFromPlatforms(array $platforms): string
    {
        $parts = ['video url tool', 'video link generator'];
        foreach (self::platformNames($platforms) as $name) {
            $parts[] = strtolower($name);
        }

        return implode(', ', array_unique($parts));
    }

    /**
     * Keeps FAQ answers in sync with currently enabled providers.
     *
     * @param array<int, array{q: string, a: string}> $faq
     * @param array<string, array> $platforms
     * @return array<int, array{q: string, a: string}>
     */
    public static function applyDynamicFaqAnswers(array $faq, array $platforms): array
    {
        $answer = self::supportedPlatformsSentence($platforms);

        foreach ($faq as $i => $item) {
            $question = strtolower(trim((string) ($item['q'] ?? '')));
            if (str_contains($question, 'which platforms') || str_contains($question, 'platforms are supported')) {
                $faq[$i]['a'] = $answer;
            }
        }

        return $faq;
    }
}
