<?php

declare(strict_types=1);

namespace App\Provider\Instagram;

require_once __DIR__ . '/instagramDlp.php';
use App\HttpClient;

class Extractor
{
    private HttpClient $http;
    private InstagramDlp $ytdlp;

    public function __construct(HttpClient $http, array $config = [])
    {
        $this->http = $http;
        $this->ytdlp = new InstagramDlp($config);
    }

    public function extract(string $url): array
    {
        $links = $this->ytdlp->extract($url);
        if ($links !== []) {
            return $links;
        }

        return $this->extractFromPage($url);
    }

    private function extractFromPage(string $url): array
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
            return [[
                'type' => 'download',
                'label' => 'Instagram Video MP4',
                'url' => stripcslashes($m[1]),
                'quality' => 'HD',
                'download' => true,
                'ext' => 'mp4',
            ]];
        }

        return [];
    }
}
