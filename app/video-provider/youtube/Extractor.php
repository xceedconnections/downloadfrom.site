<?php

declare(strict_types=1);

namespace App\Provider\Youtube;

use App\HttpClient;

require_once __DIR__ . '/youtubeDlp.php';

class Extractor
{
    private HttpClient $http;
    private YoutubeDlp $ytdlp;

    /** @param array<string, mixed> $config */
    public function __construct(HttpClient $http, array $config = [])
    {
        $this->http = $http;
        $this->ytdlp = new YoutubeDlp($config);
    }

    public function extract(string $url): array
    {
        $links = $this->ytdlp->extract($url);
        if ($links !== []) {
            return $links;
        }

        $links = $this->extractViaInnertube($url);
        if ($links !== []) {
            return $links;
        }

        return $this->extractViaPageScrape($url);
    }

    private function extractViaInnertube(string $url): array
    {
        $videoId = $this->videoId($url);
        if ($videoId === null) {
            return [];
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

        if (!$response['success'] || empty($response['body'])) {
            return [];
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            return [];
        }

        return $this->parseStreamFormats($data);
    }

    /** @param array<string, mixed> $data */
    private function parseStreamFormats(array $data): array
    {
        $streams = $data['streamingData'] ?? null;
        if (!is_array($streams)) {
            return [];
        }

        $allFormats = array_merge(
            $streams['formats'] ?? [],
            $streams['adaptiveFormats'] ?? []
        );

        $links = [];
        $seen = [];

        usort($allFormats, static fn(array $a, array $b): int => ($b['height'] ?? 0) <=> ($a['height'] ?? 0));

        foreach ($allFormats as $format) {
            $mediaUrl = $format['url'] ?? null;
            if ($mediaUrl === null) {
                continue;
            }

            if (isset($seen[$mediaUrl]) || str_contains((string) $mediaUrl, '.m3u8')) {
                continue;
            }

            $seen[$mediaUrl] = true;
            $mime = (string) ($format['mimeType'] ?? '');
            $isVideo = str_contains($mime, 'video');
            $isAudio = str_contains($mime, 'audio');

            if (!$isVideo && !$isAudio) {
                continue;
            }

            $height = (int) ($format['height'] ?? 0);
            $hasAudio = !empty($format['audioQuality']) || str_contains($mime, 'audio/mp4');
            $label = $isAudio
                ? 'Audio' . (!empty($format['bitrate']) ? ' (' . round((int) $format['bitrate'] / 1000) . ' kbps)' : '')
                : ($height > 0 ? "{$height}p MP4" : 'Video MP4') . ($isVideo && !$hasAudio ? ' (Video only)' : '');

            $links[] = [
                'type' => 'download',
                'label' => $label,
                'url' => $mediaUrl,
                'quality' => $isAudio ? 'audio' : ($height > 0 ? "{$height}p" : 'video'),
                'download' => true,
                'ext' => $isAudio ? 'm4a' : 'mp4',
                'combined' => $isVideo && $hasAudio,
            ];
        }

        return array_slice($links, 0, 10);
    }

    private function extractViaPageScrape(string $url): array
    {
        $videoId = $this->videoId($url);
        if ($videoId === null) {
            return [];
        }

        $response = $this->http->get(
            "https://www.youtube.com/watch?v={$videoId}",
            [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Cookie: CONSENT=YES+1',
            ]
        );

        if (!$response['success'] || empty($response['body'])) {
            return [];
        }

        $data = $this->parsePlayerJson($response['body'], 'ytInitialPlayerResponse');
        if ($data === null) {
            return [];
        }

        return $this->parseStreamFormats($data);
    }

    private function parsePlayerJson(string $html, string $variable): ?array
    {
        $marker = "{$variable} = ";
        $pos = strpos($html, $marker);
        if ($pos === false) {
            return null;
        }

        $start = $pos + strlen($marker);
        $json = $this->extractJsonObject($html, $start);
        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    private function extractJsonObject(string $html, int $start): ?string
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

    private function videoId(string $url): ?string
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
