<?php

declare(strict_types=1);

namespace App\Provider\Reddit;

require_once __DIR__ . '/redditDlp.php';
use App\HttpClient;

class Extractor
{
    private HttpClient $http;
    private RedditDlp $ytdlp;

    public function __construct(HttpClient $http, array $config = [])
    {
        $this->http = $http;
        $this->ytdlp = new RedditDlp($config);
    }

    public function extract(string $url): array
    {
        $links = $this->ytdlp->extract($url);
        if ($links !== []) {
            return $links;
        }

        return $this->extractViaJsonApi($url);
    }

    private function extractViaJsonApi(string $url): array
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

        $fallback = $post['media']['reddit_video']['fallback_url']
            ?? $post['secure_media']['reddit_video']['fallback_url']
            ?? null;

        if (!$fallback) {
            return [];
        }

        return [[
            'type' => 'download',
            'label' => 'Reddit Video MP4',
            'url' => html_entity_decode($fallback),
            'quality' => ($post['media']['reddit_video']['height'] ?? 'SD') . 'p',
            'download' => true,
            'ext' => 'mp4',
        ]];
    }
}
