<?php

declare(strict_types=1);

namespace App;

/**
 * YouTube InnerTube / page-scrape fallback when yt-dlp returns no usable streams.
 */
class YoutubeInnertube
{
    /** @var list<array{0: string, 1: string, 2: string}> */
    private const PLAYER_CLIENTS = [
        ['ANDROID', '19.45.36', 'com.google.android.youtube/19.45.36 (Linux; U; Android 11) gzip'],
        ['IOS', '19.45.4', 'com.google.ios.youtube/19.45.4 (iPhone14,3; U; CPU iOS 15_6 like Mac OS X)'],
        ['TVHTML5_SIMPLY_EMBEDDED_PLAYER', '2.0', 'Mozilla/5.0 (ChromiumStylePlatform) Cobalt/Version'],
        ['WEB', '2.20250101.00.00', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'],
    ];

    public function __construct(private HttpClient $http)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function extractVideoLinks(string $url): array
    {
        $playerData = $this->fetchPlayerData($url);
        if ($playerData === null) {
            return [];
        }

        return $this->parseVideoFormats($playerData);
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

        foreach (self::PLAYER_CLIENTS as [$clientName, $clientVersion, $userAgent]) {
            $payload = json_encode([
                'context' => [
                    'client' => [
                        'clientName' => $clientName,
                        'clientVersion' => $clientVersion,
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
                    'User-Agent: ' . $userAgent,
                    'Origin: https://www.youtube.com',
                    'Referer: https://www.youtube.com/',
                ]
            );

            if (!$response['success'] || empty($response['body'])) {
                continue;
            }

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
    private function parseVideoFormats(array $data): array
    {
        $streams = $data['streamingData'] ?? null;
        if (!is_array($streams)) {
            return [];
        }

        $allFormats = array_merge(
            $streams['formats'] ?? [],
            $streams['adaptiveFormats'] ?? []
        );

        $bestCombined = [];
        $bestVideo = [];

        foreach ($allFormats as $format) {
            if (!is_array($format)) {
                continue;
            }

            $mediaUrl = self::formatUrl($format);
            if ($mediaUrl === null || str_contains($mediaUrl, '.m3u8')) {
                continue;
            }

            $mime = (string) ($format['mimeType'] ?? '');
            if (!str_contains($mime, 'video')) {
                continue;
            }

            $height = (int) ($format['height'] ?? 0);
            if ($height <= 0) {
                continue;
            }

            $hasAudio = !empty($format['audioQuality']) || str_contains($mime, 'audio/mp4');
            if ($hasAudio) {
                if (!isset($bestCombined[$height]) || $height >= (int) ($bestCombined[$height]['height'] ?? 0)) {
                    $bestCombined[$height] = $format + ['_url' => $mediaUrl, '_combined' => true];
                }
            } elseif (!isset($bestVideo[$height])) {
                $bestVideo[$height] = $format + ['_url' => $mediaUrl, '_combined' => false];
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

            $combined = !empty($format['_combined']);
            $label = "{$height}p MP4" . ($combined ? '' : ' (Video only)');

            $links[] = [
                'type' => 'download',
                'label' => $label,
                'url' => (string) $format['_url'],
                'quality' => "{$height}p",
                'download' => true,
                'ext' => 'mp4',
                'combined' => $combined,
            ];
        }

        return $links;
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

            $mediaUrl = self::formatUrl($format);
            if ($mediaUrl === null || str_contains($mediaUrl, '.m3u8')) {
                continue;
            }

            $mime = (string) ($format['mimeType'] ?? '');
            if (!str_contains($mime, 'audio')) {
                continue;
            }

            if (str_contains($mime, 'webm') || str_contains($mime, 'opus')) {
                continue;
            }

            $abr = self::audioBitrate($format);
            if ($abr <= 0) {
                continue;
            }

            $key = (string) $abr;
            if (!isset($bestByBitrate[$key]) || self::audioBitrate($bestByBitrate[$key]) < $abr) {
                $bestByBitrate[$key] = $format + ['_url' => $mediaUrl];
            }
        }

        if ($bestByBitrate === []) {
            return [];
        }

        uksort($bestByBitrate, static fn(string $a, string $b): int => (int) $b <=> (int) $a);

        $links = [];
        foreach ($bestByBitrate as $key => $format) {
            $kbps = max(1, (int) $key);
            $links[] = [
                'type' => 'download',
                'label' => "MP3 {$kbps} kbps AAC",
                'url' => (string) $format['_url'],
                'quality' => "{$kbps}k",
                'download' => true,
                'ext' => 'mp3',
                'source_ext' => 'm4a',
                'combined' => false,
            ];
        }

        return array_slice(array_values($links), 0, 8);
    }

    /** @param array<string, mixed> $format */
    private static function formatUrl(array $format): ?string
    {
        $mediaUrl = (string) ($format['url'] ?? '');
        if ($mediaUrl !== '') {
            return $mediaUrl;
        }

        $cipher = (string) ($format['signatureCipher'] ?? $format['cipher'] ?? '');
        if ($cipher === '') {
            return null;
        }

        parse_str($cipher, $parts);
        $baseUrl = (string) ($parts['url'] ?? '');
        if ($baseUrl === '') {
            return null;
        }

        $sig = (string) ($parts['s'] ?? '');
        if ($sig === '') {
            return $baseUrl;
        }

        $sp = (string) ($parts['sp'] ?? 'sig');
        return $baseUrl . '&' . $sp . '=' . urlencode(urldecode($sig));
    }

    /** @param array<string, mixed> $format */
    private static function audioBitrate(array $format): int
    {
        $abr = (int) round((float) ($format['abr'] ?? $format['tbr'] ?? 0));
        if ($abr > 0) {
            return $abr;
        }

        $bitrate = (int) ($format['bitrate'] ?? $format['averageBitrate'] ?? 0);
        return $bitrate > 0 ? (int) round($bitrate / 1000) : 0;
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
