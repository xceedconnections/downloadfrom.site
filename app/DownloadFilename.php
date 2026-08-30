<?php

declare(strict_types=1);

namespace App;

/**
 * Builds safe download filenames from result metadata.
 */
final class DownloadFilename
{
    /** @param array<string, mixed> $data @param array<string, mixed> $link */
    public static function build(array $data, array $link): string
    {
        $title = self::sanitizeTitle((string) ($data['title'] ?? 'download'));
        $isAudio = self::isAudioLink($data, $link);
        $ext = strtolower((string) ($link['ext'] ?? ($isAudio ? 'mp3' : 'mp4')));
        if ($isAudio) {
            $ext = 'mp3';
        }

        $suffix = self::qualitySuffix((string) ($link['quality'] ?? ''), $isAudio);
        $maxBase = max(16, 120 - strlen($suffix) - strlen($ext) - 1);
        $base = substr($title, 0, $maxBase) ?: 'download';

        return $base . $suffix . '.' . $ext;
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $link */
    public static function contentDisposition(array $data, array $link): string
    {
        $filename = self::build($data, $link);
        $ascii = self::asciiFallback($filename);

        if ($ascii === $filename) {
            return 'attachment; filename="' . $ascii . '"';
        }

        return 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $link */
    private static function isAudioLink(array $data, array $link): bool
    {
        if (($link['service_type'] ?? '') === 'audio') {
            return true;
        }

        if (($data['service'] ?? '') === ServiceConfig::SERVICE_AUDIO) {
            return true;
        }

        $quality = (string) ($link['quality'] ?? '');

        return str_ends_with($quality, 'k');
    }

    private static function qualitySuffix(string $quality, bool $isAudio): string
    {
        if ($quality === '') {
            return '';
        }

        if ($isAudio && str_ends_with($quality, 'k')) {
            return '_' . preg_replace('/\D+/', '', $quality) . 'kbps';
        }

        if (str_contains($quality, 'p')) {
            return '_' . preg_replace('/\D+/', '', $quality) . 'p';
        }

        return '';
    }

    private static function sanitizeTitle(string $title): string
    {
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = preg_replace('/[\\\\\/:*?"<>|]+/u', '', $title) ?? '';
        $title = preg_replace('/[\x00-\x1f\x7f]+/u', '', $title) ?? '';
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');

        return $title !== '' ? $title : 'download';
    }

    private static function asciiFallback(string $filename): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
        if ($ascii === false || $ascii === '') {
            $ascii = preg_replace('/[^\w.\-() ]+/u', '', $filename) ?? 'download';
        }

        $ascii = trim(preg_replace('/\s+/', '_', $ascii) ?? '');

        return $ascii !== '' ? $ascii : 'download.mp4';
    }
}
