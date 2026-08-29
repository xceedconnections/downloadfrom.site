<?php

declare(strict_types=1);

namespace App;

/**
 * Builds download link arrays from yt-dlp format metadata.
 */
final class YtDlpFormatLinks
{
    /** @param array<int, array<string, mixed>> $formats @return array<int, array<string, mixed>> */
    public static function buildVideoLinks(array $formats): array
    {
        $bestCombined = [];
        $bestVideo = [];

        foreach ($formats as $format) {
            if (empty($format['url']) || !self::isDirectFormat($format)) {
                continue;
            }

            $vcodec = (string) ($format['vcodec'] ?? 'none');
            $acodec = (string) ($format['acodec'] ?? 'none');
            $height = (int) ($format['height'] ?? 0);

            if ($height <= 0 || $vcodec === 'none') {
                continue;
            }

            if ($acodec !== 'none') {
                if (!isset($bestCombined[$height]) || self::isBetterFormat($format, $bestCombined[$height])) {
                    $bestCombined[$height] = $format;
                }
            } else {
                if (!isset($bestVideo[$height]) || self::isBetterFormat($format, $bestVideo[$height])) {
                    $bestVideo[$height] = $format;
                }
            }
        }

        $heights = array_unique(array_merge(array_keys($bestCombined), array_keys($bestVideo)));
        rsort($heights, SORT_NUMERIC);

        $links = [];
        foreach ($heights as $height) {
            $format = $bestCombined[$height] ?? $bestVideo[$height] ?? null;
            if ($format === null) {
                continue;
            }

            $combined = isset($bestCombined[$height]);
            $ext = strtolower((string) ($format['ext'] ?? 'mp4'));
            $filesize = (int) ($format['filesize'] ?? $format['filesize_approx'] ?? 0);
            $sizeLabel = $filesize > 0 ? ' (' . self::formatBytes($filesize) . ')' : '';
            $label = "{$height}p " . strtoupper($ext) . $sizeLabel;
            if (!$combined) {
                $label .= ' (Video only)';
            }

            $links[] = self::videoLink($format, $label, "{$height}p", $ext, $combined);
        }

        return $links;
    }

    /** @param array<int, array<string, mixed>> $formats @return array<int, array<string, mixed>> */
    public static function buildAudioMp3Links(array $formats): array
    {
        $candidates = [];

        foreach ($formats as $format) {
            if (empty($format['url']) || !self::isDirectFormat($format)) {
                continue;
            }

            $vcodec = (string) ($format['vcodec'] ?? 'none');
            $acodec = (string) ($format['acodec'] ?? 'none');
            if ($vcodec !== 'none' || $acodec === 'none') {
                continue;
            }

            $sourceExt = strtolower((string) ($format['ext'] ?? ''));
            if (!self::isMp3CompatibleAudioExt($sourceExt)) {
                continue;
            }

            $abr = (int) round((float) ($format['abr'] ?? $format['tbr'] ?? 0));
            if ($abr <= 0) {
                continue;
            }

            $key = (string) $abr;
            if (!isset($candidates[$key]) || self::isBetterAudioFormat($format, $candidates[$key])) {
                $candidates[$key] = $format;
            }
        }

        if ($candidates === []) {
            return [];
        }

        uksort($candidates, static fn(string $a, string $b): int => (int) $b <=> (int) $a);

        $links = [];
        foreach ($candidates as $abr => $format) {
            $sourceExt = strtolower((string) ($format['ext'] ?? 'm4a'));
            $filesize = (int) ($format['filesize'] ?? $format['filesize_approx'] ?? 0);
            $sizeLabel = $filesize > 0 ? ' (' . self::formatBytes($filesize) . ')' : '';
            $codecLabel = $sourceExt === 'mp3' ? '' : ' AAC';

            $links[] = [
                'type' => 'download',
                'label' => "MP3 {$abr} kbps{$codecLabel}{$sizeLabel}",
                'url' => $format['url'],
                'quality' => "{$abr}k",
                'download' => true,
                'ext' => 'mp3',
                'source_ext' => $sourceExt,
                'format_id' => $format['format_id'] ?? null,
                'combined' => false,
                'filesize' => $filesize > 0 ? $filesize : null,
                'abr' => $abr,
            ];
        }

        return $links;
    }

    /** @param array<string, mixed> $format */
    private static function videoLink(array $format, string $label, string $quality, string $ext, bool $combined): array
    {
        $filesize = (int) ($format['filesize'] ?? $format['filesize_approx'] ?? 0);

        return [
            'type' => 'download',
            'label' => $label,
            'url' => $format['url'],
            'quality' => $quality,
            'download' => true,
            'ext' => $ext,
            'format_id' => $format['format_id'] ?? null,
            'combined' => $combined,
            'filesize' => $filesize > 0 ? $filesize : null,
        ];
    }

    private static function isMp3CompatibleAudioExt(string $ext): bool
    {
        return in_array($ext, ['m4a', 'mp4', 'mp3', 'aac'], true);
    }

    /** @param array<string, mixed> $format */
    private static function isDirectFormat(array $format): bool
    {
        $protocol = strtolower((string) ($format['protocol'] ?? ''));
        $url = (string) ($format['url'] ?? '');
        $ext = strtolower((string) ($format['ext'] ?? ''));

        if ($ext === 'mhtml' || str_starts_with((string) ($format['format_id'] ?? ''), 'sb')) {
            return false;
        }

        if (str_contains($protocol, 'm3u8') || str_contains($protocol, 'dash')) {
            return false;
        }

        if (str_contains($url, '.m3u8') || str_contains($url, 'manifest.googlevideo.com')) {
            return false;
        }

        return $protocol === 'https' || $protocol === 'http' || $protocol === 'https+http';
    }

    /** @param array<string, mixed> $new @param array<string, mixed> $current */
    private static function isBetterFormat(array $new, array $current): bool
    {
        $newExt = strtolower((string) ($new['ext'] ?? ''));
        $curExt = strtolower((string) ($current['ext'] ?? ''));
        if ($newExt === 'mp4' && $curExt !== 'mp4') {
            return true;
        }
        if ($newExt !== 'mp4' && $curExt === 'mp4') {
            return false;
        }

        $newSize = (int) ($new['filesize'] ?? $new['filesize_approx'] ?? 0);
        $curSize = (int) ($current['filesize'] ?? $current['filesize_approx'] ?? 0);
        if ($newSize > 0 && $curSize > 0 && $newSize !== $curSize) {
            return $newSize > $curSize;
        }

        return ((int) ($new['tbr'] ?? 0)) > ((int) ($current['tbr'] ?? 0));
    }

    /** @param array<string, mixed> $new @param array<string, mixed> $current */
    private static function isBetterAudioFormat(array $new, array $current): bool
    {
        $rank = static fn(string $ext): int => match ($ext) {
            'mp3' => 3,
            'm4a' => 2,
            'aac' => 2,
            'mp4' => 1,
            default => 0,
        };

        $newExt = strtolower((string) ($new['ext'] ?? ''));
        $curExt = strtolower((string) ($current['ext'] ?? ''));
        $newRank = $rank($newExt);
        $curRank = $rank($curExt);
        if ($newRank !== $curRank) {
            return $newRank > $curRank;
        }

        return self::isBetterFormat($new, $current);
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 0) . ' KB';
        }

        return $bytes . ' B';
    }
}
