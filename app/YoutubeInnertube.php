<?php

declare(strict_types=1);

namespace App;

/**
 * YouTube InnerTube / page-scrape fallback when yt-dlp returns no usable streams.
 */
class YoutubeInnertube
{
    public function __construct(private HttpClient $http)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function extractAudioLinks(string $url): array
    {
        $playerData = $this->fetchPlayerData($url);
        if ($playerData === null) {
            return [];
        }

        return $this->parseAudioFormats($playerData);
    }

    /** @return array<string, mixed>|null */
    private function fetchPlayerData(string $url): ?array
    {
        $videoId = self::videoId($url);
        if ($videoId === null) {
            return null;
        }

        $payload = json_encode([
            'context' => [
                'client' => [
                    'clientName' => 'ANDROID',
                    'clientVersion' => '19.09.37',
                    'androidSdkVersion' => 30,
                    'hl' => 'en',
                    'gl' => 'US',
                ],
            ],
            'videoId' => $videoId,
        ]);

        $response = $this->http->post(
            'https://www.youtube.com/youtubei/v1/player?prettyPrint=false',
            $payload !== false ? $payload : '{}',
            [
                'Content-Type: application/json',
                'User-Agent: com.google.android.youtube/19.09.37 (Linux; U; Android 11) gzip',
            ]
        );

        if ($response['success'] && !empty($response['body'])) {
            $data = json_decode($response['body'], true);
            if (is_array($data) && !empty($data['streamingData'])) {
                return $data;
            }
        }

        $response = $this->http->get(
            "https://www.youtube.com/watch?v={$videoId}",
            [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Cookie: CONSENT=YES+1',
            ]
        );

        if (!$response['success'] || empty($response['body'])) {
            return null;
        }

        return self::parsePlayerJson($response['body'], 'ytInitialPlayerResponse');
    }

    /** @param array<string, mixed> $data @return array<int, array<string, mixed>> */
    private function parseAudioFormats(array $data): array
    {
        $streams = $data['streamingData'] ?? null;
        if (!is_array($streams)) {
            return [];
        }

        $allFormats = array_merge(
            $streams['adaptiveFormats'] ?? [],
            $streams['formats'] ?? []
        );

        $bestByBitrate = [];
        foreach ($allFormats as $format) {
            if (!is_array($format)) {
                continue;
            }

            $mediaUrl = (string) ($format['url'] ?? '');
            if ($mediaUrl === '' || str_contains($mediaUrl, '.m3u8')) {
                continue;
            }

            $mime = (string) ($format['mimeType'] ?? '');
            if (!str_contains($mime, 'audio')) {
                continue;
            }

            // MP3-compatible AAC streams only (skip WebM/Opus).
            if (str_contains($mime, 'webm') || str_contains($mime, 'opus')) {
                continue;
            }

            $bitrate = (int) ($format['bitrate'] ?? $format['averageBitrate'] ?? 0);
            $ext = 'm4a';
            $key = (string) max(1, (int) round($bitrate / 1000));
            if (!isset($bestByBitrate[$key]) || $bitrate > (int) ($bestByBitrate[$key]['bitrate'] ?? 0)) {
                $bestByBitrate[$key] = $format + ['_ext' => $ext, 'bitrate' => $bitrate];
            }
        }

        if ($bestByBitrate === []) {
            return [];
        }

        uasort($bestByBitrate, static fn(array $a, array $b): int => ($b['bitrate'] ?? 0) <=> ($a['bitrate'] ?? 0));

        $links = [];
        foreach ($bestByBitrate as $key => $format) {
            $kbps = max(1, (int) $key);
            $codecLabel = ' AAC';

            $links[] = [
                'type' => 'download',
                'label' => "MP3 {$kbps} kbps{$codecLabel}",
                'url' => (string) $format['url'],
                'quality' => "{$kbps}k",
                'download' => true,
                'ext' => 'mp3',
                'source_ext' => 'm4a',
                'combined' => false,
            ];
        }

        return array_slice(array_values($links), 0, 6);
    }

    /** @return array<string, mixed>|null */
    public static function parsePlayerJson(string $html, string $variable): ?array
    {
        $marker = "{$variable} = ";
        $pos = strpos($html, $marker);
        if ($pos === false) {
            return null;
        }

        $start = $pos + strlen($marker);
        $json = self::extractJsonObject($html, $start);
        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    public static function extractJsonObject(string $html, int $start): ?string
    {
        $len = strlen($html);
        while ($start < $len && ctype_space($html[$start])) {
            $start++;
        }

        if ($start >= $len || $html[$start] !== '{') {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;

        for ($i = $start; $i < $len; $i++) {
            $char = $html[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($char === '\\' && $inString) {
                $escape = true;
                continue;
            }
            if ($char === '"') {
                $inString = !$inString;
                continue;
            }
            if ($inString) {
                continue;
            }
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($html, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    public static function videoId(string $url): ?string
    {
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#youtu\.be/([a-zA-Z0-9_-]{11})#', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#/shorts/([a-zA-Z0-9_-]{11})#', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
