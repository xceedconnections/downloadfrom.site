<?php

declare(strict_types=1);

namespace App;

/**
 * Extracts direct media/download URLs from platform pages and APIs.
 */
class MediaExtractor
{
    private HttpClient $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    public function extract(string $platform, string $url): array
    {
        return match ($platform) {
            'youtube' => $this->extractYouTube($url),
            'tiktok' => $this->extractTikTok($url),
            'vimeo' => $this->extractVimeo($url),
            'dailymotion' => $this->extractDailymotion($url),
            'reddit' => $this->extractReddit($url),
            'twitter' => $this->extractTwitter($url),
            'instagram' => $this->extractInstagram($url),
            'facebook' => $this->extractFacebook($url),
            default => [],
        };
    }

    public function extractYouTube(string $url): array
    {
        $extractor = new YouTubeExtractor($this->http);
        return $extractor->extract($url);
    }

    /** @deprecated use YouTubeExtractor */
    private function extractYouTubeLegacy(string $url): array
    {
        $videoId = $this->youtubeId($url);
        if ($videoId === null) {
            return [];
        }

        $response = $this->http->get(
            "https://www.youtube.com/watch?v={$videoId}",
            ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36']
        );

        if (!$response['success'] || empty($response['body'])) {
            return $this->youtubeViaInnertube($videoId);
        }

        $links = [];
        $playerData = null;

        if (preg_match('/ytInitialPlayerResponse\s*=\s*(\{.+?\})\s*;/s', $response['body'], $m)) {
            $playerData = json_decode($m[1], true);
        } elseif (preg_match('/var\s+ytInitialPlayerResponse\s*=\s*(\{.+?\});/s', $response['body'], $m)) {
            $playerData = json_decode($m[1], true);
        }

        if (is_array($playerData)) {
            $links = $this->parseYouTubeStreams($playerData);
        }

        if ($links === []) {
            $links = $this->youtubeViaInnertube($videoId);
        }

        return $links;
    }

    private function youtubeViaInnertube(string $videoId): array
    {
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
            $payload,
            [
                'Content-Type: application/json',
                'User-Agent: com.google.android.youtube/19.09.37 (Linux; U; Android 11) gzip',
            ]
        );

        if (!$response['success'] || empty($response['body'])) {
            return [];
        }

        $data = json_decode($response['body'], true);
        return is_array($data) ? $this->parseYouTubeStreams($data) : [];
    }

    private function parseYouTubeStreams(array $data): array
    {
        $streams = $data['streamingData'] ?? null;
        if (!is_array($streams)) {
            return [];
        }

        $links = [];
        $allFormats = array_merge(
            $streams['formats'] ?? [],
            $streams['adaptiveFormats'] ?? []
        );

        usort($allFormats, static fn(array $a, array $b): int => ($b['height'] ?? 0) <=> ($a['height'] ?? 0));

        $seen = [];
        foreach ($allFormats as $format) {
            $mediaUrl = $format['url'] ?? null;
            if ($mediaUrl === null && !empty($format['signatureCipher'])) {
                parse_str($format['signatureCipher'], $cipher);
                if (!empty($cipher['url'])) {
                    $mediaUrl = $cipher['url'];
                }
            }

            if ($mediaUrl === null || isset($seen[$mediaUrl])) {
                continue;
            }

            $seen[$mediaUrl] = true;
            $height = (int) ($format['height'] ?? 0);
            $ext = $format['mimeType'] ?? '';
            $isVideo = str_contains($ext, 'video');
            $isAudio = str_contains($ext, 'audio');

            if (!$isVideo && !$isAudio) {
                continue;
            }

            $label = $isAudio
                ? 'Audio' . (!empty($format['bitrate']) ? ' (' . round($format['bitrate'] / 1000) . ' kbps)' : '')
                : ($height > 0 ? "{$height}p" : 'Video') . ($isVideo && $height === 0 ? ' (MP4)' : '');

            $links[] = [
                'type' => 'download',
                'label' => $label,
                'url' => $mediaUrl,
                'quality' => $height > 0 ? "{$height}p" : 'audio',
                'download' => true,
                'ext' => $isAudio ? 'mp3' : 'mp4',
            ];
        }

        return array_slice($links, 0, 8);
    }

    public function extractTikTok(string $url): array
    {
        $links = $this->extractTikTokViaApi($url);
        if ($links !== []) {
            return $links;
        }

        $resolved = $this->resolveTikTokUrl($url);
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
                    $mediaUrl = $this->decodeTikTokUrl($raw);
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

    private function extractTikTokViaApi(string $url): array
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

        return [];
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

    private function resolveTikTokUrl(string $url): string
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

    private function decodeTikTokUrl(string $raw): ?string
    {
        $mediaUrl = stripcslashes($raw);
        $mediaUrl = str_replace(['\\u002F', '\\/'], '/', $mediaUrl);
        $mediaUrl = html_entity_decode($mediaUrl);

        return filter_var($mediaUrl, FILTER_VALIDATE_URL) ? $mediaUrl : null;
    }

    public function extractVimeo(string $url): array
    {
        if (!preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            return [];
        }

        $response = $this->http->get(
            "https://player.vimeo.com/video/{$m[1]}/config",
            ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36']
        );

        if (!$response['success'] || empty($response['body'])) {
            return [];
        }

        $data = json_decode($response['body'], true);
        $files = $data['request']['files']['progressive'] ?? [];

        usort($files, static fn(array $a, array $b): int => ($b['height'] ?? 0) <=> ($a['height'] ?? 0));

        $links = [];
        foreach ($files as $file) {
            if (empty($file['url'])) {
                continue;
            }
            $height = (int) ($file['height'] ?? 0);
            $links[] = [
                'type' => 'download',
                'label' => $height > 0 ? "{$height}p MP4" : 'MP4',
                'url' => $file['url'],
                'quality' => "{$height}p",
                'download' => true,
                'ext' => 'mp4',
            ];
        }

        return $links;
    }

    public function extractDailymotion(string $url): array
    {
        $videoId = null;
        if (preg_match('#dailymotion\.com/video/([a-zA-Z0-9]+)#', $url, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('#dai\.ly/([a-zA-Z0-9]+)#', $url, $m)) {
            $videoId = $m[1];
        }

        if ($videoId === null) {
            return [];
        }

        $response = $this->http->get(
            "https://www.dailymotion.com/player/metadata/video/{$videoId}",
            ['User-Agent: Mozilla/5.0']
        );

        if (!$response['success'] || empty($response['body'])) {
            return [];
        }

        $data = json_decode($response['body'], true);
        $qualities = $data['qualities'] ?? [];
        $links = [];

        foreach ($qualities as $quality => $info) {
            if (empty($info['url'])) {
                continue;
            }
            $links[] = [
                'type' => 'download',
                'label' => strtoupper((string) $quality) . ' MP4',
                'url' => $info['url'],
                'quality' => (string) $quality,
                'download' => true,
                'ext' => 'mp4',
            ];
        }

        usort($links, static fn(array $a, array $b): int => strcmp($b['quality'], $a['quality']));

        return $links;
    }

    public function extractReddit(string $url): array
    {
        if (preg_match('#v\.redd\.it/([a-zA-Z0-9]+)#', $url, $m)) {
            return [[
                'type' => 'download',
                'label' => 'Reddit Video MP4',
                'url' => "https://v.redd.it/{$m[1]}/DASH_720.mp4",
                'quality' => '720p',
                'download' => true,
                'ext' => 'mp4',
            ]];
        }

        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['path'])) {
            return [];
        }

        $jsonUrl = 'https://www.reddit.com' . rtrim($parsed['path'], '/') . '.json?raw_json=1';
        $response = $this->http->get($jsonUrl, ['User-Agent: VideoLink/1.0']);

        if (!$response['success'] || empty($response['body'])) {
            return [];
        }

        $data = json_decode($response['body'], true);
        $post = $data[0]['data']['children'][0]['data'] ?? null;
        if ($post === null) {
            return [];
        }

        $links = [];
        $fallback = $post['media']['reddit_video']['fallback_url']
            ?? $post['secure_media']['reddit_video']['fallback_url']
            ?? null;

        if ($fallback) {
            $links[] = [
                'type' => 'download',
                'label' => 'Reddit Video MP4',
                'url' => html_entity_decode($fallback),
                'quality' => ($post['media']['reddit_video']['height'] ?? 'SD') . 'p',
                'download' => true,
                'ext' => 'mp4',
            ];
        }

        return $links;
    }

    public function extractTwitter(string $url): array
    {
        $response = $this->http->get(
            'https://publish.twitter.com/oembed?url=' . urlencode($url) . '&omit_script=1',
            ['User-Agent: Mozilla/5.0']
        );

        if (!$response['success'] || empty($response['body'])) {
            return [];
        }

        $pageResponse = $this->http->get(
            $url,
            ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36']
        );

        if (!$pageResponse['success'] || empty($pageResponse['body'])) {
            return [];
        }

        $links = [];
        if (preg_match_all('#https://video\.twimg\.com/[^"\']+\.mp4#', $pageResponse['body'], $matches)) {
            $urls = array_unique($matches[0]);
            foreach ($urls as $i => $mediaUrl) {
                $links[] = [
                    'type' => 'download',
                    'label' => 'Video MP4' . (count($urls) > 1 ? ' #' . ($i + 1) : ''),
                    'url' => html_entity_decode($mediaUrl),
                    'quality' => 'MP4',
                    'download' => true,
                    'ext' => 'mp4',
                ];
            }
        }

        return $links;
    }

    public function extractInstagram(string $url): array
    {
        $response = $this->http->get(
            $url . (str_contains($url, '?') ? '&' : '?') . '__a=1&__d=dis',
            ['User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15']
        );

        if ($response['success'] && !empty($response['body'])) {
            $data = json_decode($response['body'], true);
            $videoUrl = $data['graphql']['shortcode_media']['video_url']
                ?? $data['items'][0]['video_versions'][0]['url']
                ?? null;

            if ($videoUrl) {
                return [[
                    'type' => 'download',
                    'label' => 'Instagram Video MP4',
                    'url' => $videoUrl,
                    'quality' => 'HD',
                    'download' => true,
                    'ext' => 'mp4',
                ]];
            }
        }

        $pageResponse = $this->http->get($url, ['User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)']);
        if ($pageResponse['success'] && preg_match('/"video_url"\s*:\s*"([^"]+)"/', $pageResponse['body'], $m)) {
            $mediaUrl = stripcslashes($m[1]);
            return [[
                'type' => 'download',
                'label' => 'Instagram Video MP4',
                'url' => $mediaUrl,
                'quality' => 'HD',
                'download' => true,
                'ext' => 'mp4',
            ]];
        }

        return [];
    }

    public function extractFacebook(string $url): array
    {
        $response = $this->http->get($url, ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36']);
        if (!$response['success'] || empty($response['body'])) {
            return [];
        }

        $links = [];
        if (preg_match_all('#https://[^"\']+\.fbcdn\.net/[^"\']+\.mp4[^"\']*#', $response['body'], $matches)) {
            $urls = array_unique($matches[0]);
            foreach (array_slice($urls, 0, 3) as $i => $mediaUrl) {
                $links[] = [
                    'type' => 'download',
                    'label' => 'Facebook Video MP4' . (count($urls) > 1 ? ' #' . ($i + 1) : ''),
                    'url' => html_entity_decode(str_replace('\\/', '/', $mediaUrl)),
                    'quality' => 'MP4',
                    'download' => true,
                    'ext' => 'mp4',
                ];
            }
        }

        return $links;
    }

    private function youtubeId(string $url): ?string
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
