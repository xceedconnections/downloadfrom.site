<?php

declare(strict_types=1);

namespace App;

/**
 * Blocks downloads when channel / author matches admin blocklist.
 */
class ChannelBlocker
{
    public static function isBlocked(Settings $settings, string $platform, array $data, string $serviceType = 'video'): bool
    {
        $blocked = PlatformConfig::blockedChannels($settings, $platform, $serviceType);
        if ($blocked === []) {
            return false;
        }

        $candidates = self::collectCandidates($data);
        if ($candidates === []) {
            return false;
        }

        foreach ($blocked as $blockEntry) {
            $blockNorm = self::normalize($blockEntry);
            if ($blockNorm === '') {
                continue;
            }
            foreach ($candidates as $candidate) {
                if (self::matches($blockNorm, self::normalize($candidate))) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return string[] */
    private static function collectCandidates(array $data): array
    {
        $candidates = [];
        foreach (['author', 'channel', 'uploader', 'subreddit'] as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                $candidates[] = $data[$key];
            }
        }

        $url = $data['normalized_url'] ?? $data['original_url'] ?? '';
        if (is_string($url) && $url !== '') {
            if (preg_match('#tiktok\.com/@([^/?]+)#i', $url, $m)) {
                $candidates[] = '@' . $m[1];
            }
            if (preg_match('#(?:youtube\.com|youtu\.be)/(?:c/|channel/|@)([^/?&]+)#i', $url, $m)) {
                $candidates[] = $m[1];
            }
            if (preg_match('#(?:twitter|x)\.com/([^/?]+)/#i', $url, $m)) {
                $candidates[] = '@' . $m[1];
            }
            if (preg_match('#instagram\.com/([^/?]+)/#i', $url, $m) && !in_array($m[1], ['p', 'reel', 'reels', 'stories', 'tv'], true)) {
                $candidates[] = '@' . $m[1];
            }
            if (preg_match('#twitch\.tv/([^/?]+)#i', $url, $m) && !in_array(strtolower($m[1]), ['videos', 'directory', 'settings', 'clips'], true)) {
                $candidates[] = $m[1];
            }
        }

        return array_values(array_unique($candidates));
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = ltrim($value, '@');
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }

    private static function matches(string $block, string $candidate): bool
    {
        if ($block === '' || $candidate === '') {
            return false;
        }
        if ($block === $candidate) {
            return true;
        }

        return str_contains($candidate, $block) || str_contains($block, $candidate);
    }
}
