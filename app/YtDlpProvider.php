<?php

declare(strict_types=1);

namespace App;

/**
 * Shared yt-dlp fetch + link building for all video/audio providers.
 */
final class YtDlpProvider
{
    /** @var array<string, array<string, mixed>|null> */
    private static array $jsonCache = [];

    /** @param array<string, mixed> $config @return array<string, mixed>|null */
    public static function fetchJson(string $url, array $config): ?array
    {
        $key = hash('sha256', $url);
        if (!array_key_exists($key, self::$jsonCache)) {
            self::$jsonCache[$key] = YtDlpHelper::fetchJson($url, $config);
        }

        return self::$jsonCache[$key];
    }

    public static function clearRequestCache(): void
    {
        self::$jsonCache = [];
    }

    /** @param array<string, mixed> $config @return array<int, array<string, mixed>> */
    public static function extractVideo(string $url, array $config): array
    {
        $data = self::fetchJson($url, $config);
        if ($data === null) {
            return [];
        }

        return YtDlpFormatLinks::buildVideoLinks($data['formats'] ?? []);
    }

    /** @param array<string, mixed> $config @return array<int, array<string, mixed>> */
    public static function extractAudioMp3(string $url, array $config): array
    {
        $data = self::fetchJson($url, $config);
        if ($data === null) {
            return [];
        }

        return YtDlpFormatLinks::buildAudioMp3Links($data['formats'] ?? []);
    }
}
