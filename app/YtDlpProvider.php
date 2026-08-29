<?php

declare(strict_types=1);

namespace App;

/**
 * Shared yt-dlp fetch + link building for all video/audio providers.
 */
final class YtDlpProvider
{
    /** @param array<string, mixed> $config @return array<int, array<string, mixed>> */
    public static function extractVideo(string $url, array $config): array
    {
        $data = YtDlpHelper::fetchJson($url, $config);
        if ($data === null) {
            return [];
        }

        return YtDlpFormatLinks::buildVideoLinks($data['formats'] ?? []);
    }

    /** @param array<string, mixed> $config @return array<int, array<string, mixed>> */
    public static function extractAudioMp3(string $url, array $config): array
    {
        $data = YtDlpHelper::fetchJson($url, $config);
        if ($data === null) {
            return [];
        }

        return YtDlpFormatLinks::buildAudioMp3Links($data['formats'] ?? []);
    }
}
