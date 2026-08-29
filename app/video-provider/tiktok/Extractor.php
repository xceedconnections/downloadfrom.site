<?php

declare(strict_types=1);

namespace App\Provider\Tiktok;

require_once __DIR__ . '/tiktokDlp.php';
use App\HttpClient;

class Extractor
{
    private HttpClient $http;
    private TiktokDlp $ytdlp;

    public function __construct(HttpClient $http, array $config = [])
    {
        $this->http = $http;
        $this->ytdlp = new TiktokDlp($config);
    }

    public function extract(string $url): array
    {
        $links = $this->ytdlp->extract($url);
        if ($links !== []) {
            return $links;
        }

        return $this->extractViaTikwm($url);
    }

    private function extractViaTikwm(string $url): array
    {
        $endpoints = [
            'https://www.tikwm.com/api/?hd=1&url=' . urlencode($url),
            'https://www.tikwm.com/api/?url=' . urlencode($url),
        ];

        foreach ($endpoints as $apiUrl) {
            $response = $this->http->get($apiUrl, [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept: application/json',
            ]);

            if (!$response['success'] || empty($response['body'])) {
                continue;
            }

            $data = json_decode($response['body'], true);
            if (!is_array($data) || (int) ($data['code'] ?? 1) !== 0) {
                continue;
            }

            $links = $this->linksFromTikwmData($data['data'] ?? []);
            if ($links !== []) {
                return $links;
            }
        }

        return $this->extractFromPage($url);
    }

    /** @param array<string, mixed> $video */
    private function linksFromTikwmData(array $video): array
    {
        if ($video === []) {
            return [];
        }

        $links = [];
        $candidates = [
            ['key' => 'hdplay', 'label' => 'HD MP4 (no watermark)', 'quality' => 'HD'],
            ['key' => 'play', 'label' => 'SD MP4', 'quality' => 'SD'],
            ['key' => 'wmplay', 'label' => 'MP4 (with watermark)', 'quality' => 'WM'],
        ];

        foreach ($candidates as $item) {
            $mediaUrl = $video[$item['key']] ?? null;
            if (is_string($mediaUrl) && filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
                $size = (int) ($video['size'] ?? 0);
                $sizeLabel = $size > 0 ? ' (' . round($size / 1048576, 1) . ' MB)' : '';
                $links[] = [
                    'type' => 'download',
                    'label' => $item['label'] . $sizeLabel,
                    'url' => $mediaUrl,
                    'quality' => $item['quality'],
                    'download' => true,
                    'ext' => 'mp4',
                ];
            }
        }

        return $links;
    }

    private function extractFromPage(string $url): array
    {
        $resolved = $this->resolveUrl($url);
        $response = $this->http->get(
            $resolved,
            [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept: text/html,application/xhtml+xml',
            ]
        );

        if (!$response['success'] || empty($response['body'])) {
            return [];
        }

        $body = $response['body'];
        $links = [];
        $found = [];

        $patterns = [
            '/"downloadAddr"\s*:\s*"([^"]+)"/',
            '/"playAddr"\s*:\s*"([^"]+)"/',
            '/"playApi"\s*:\s*"([^"]+)"/',
            '/"url_list"\s*:\s*\[\s*"([^"]+)"/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $body, $matches)) {
                foreach ($matches[1] as $raw) {
                    $mediaUrl = $this->decodeUrl($raw);
                    if ($mediaUrl !== null && !isset($found[$mediaUrl])) {
                        $found[$mediaUrl] = true;
                        $links[] = [
                            'type' => 'download',
                            'label' => str_contains($pattern, 'download') ? 'Download MP4' : 'Video MP4',
                            'url' => $mediaUrl,
                            'quality' => 'HD',
                            'download' => true,
                            'ext' => 'mp4',
                        ];
                    }
                }
            }
        }

        return $links;
    }

    private function resolveUrl(string $url): string
    {
        if (preg_match('#tiktok\.com/.+/video/#', $url)) {
            return $url;
        }

        $response = $this->http->get($url, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        if ($response['success'] && !empty($response['effective_url'])) {
            return $response['effective_url'];
        }

        return $url;
    }

    private function decodeUrl(string $raw): ?string
    {
        $mediaUrl = stripcslashes($raw);
        $mediaUrl = str_replace(['\\u002F', '\\/'], '/', $mediaUrl);
        $mediaUrl = html_entity_decode($mediaUrl);

        return filter_var($mediaUrl, FILTER_VALIDATE_URL) ? $mediaUrl : null;
    }
}
