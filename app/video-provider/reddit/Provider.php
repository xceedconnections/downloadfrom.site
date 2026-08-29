<?php

declare(strict_types=1);

namespace App\Provider\Reddit;

class Provider extends \App\Provider\AbstractProvider
{
public function getMetadata(string $url): array
    {
        $jsonUrl = $this->toJsonUrl($url);
        if ($jsonUrl === null) {
            return [
                'success' => true,
                'data' => [
                    'title' => 'Reddit Video',
                    'author' => null,
                    'thumbnail' => null,
                    'duration' => null,
                    'platform_name' => 'Reddit',
                ],
            ];
        }

        $response = $this->http->get($jsonUrl, ['User-Agent: VideoLink/1.0']);
        if (!$response['success'] || empty($response['body'])) {
            return [
                'success' => false,
                'error' => 'fetch_failed',
                'message' => 'Unable to retrieve Reddit post information.',
            ];
        }

        $data = json_decode($response['body'], true);
        $post = $data[0]['data']['children'][0]['data'] ?? null;

        if ($post === null) {
            return [
                'success' => false,
                'error' => 'fetch_failed',
                'message' => 'Reddit post not found or is private.',
            ];
        }

        $thumbnail = $post['thumbnail'] ?? null;
        if ($thumbnail && !str_starts_with($thumbnail, 'http')) {
            $thumbnail = null;
        }

        return [
            'success' => true,
            'data' => [
                'title' => $post['title'] ?? 'Reddit Post',
                'author' => isset($post['author']) ? 'u/' . $post['author'] : null,
                'thumbnail' => $thumbnail,
                'duration' => null,
                'platform_name' => 'Reddit',
                'subreddit' => $post['subreddit_name_prefixed'] ?? null,
            ],
        ];
    }

    public function getAvailableLinks(string $url): array
    {
        $links = [$this->watchLink($url, 'View on Reddit')];

        $jsonUrl = $this->toJsonUrl($url);
        if ($jsonUrl !== null) {
            $response = $this->http->get($jsonUrl, ['User-Agent: VideoLink/1.0']);
            if ($response['success'] && !empty($response['body'])) {
                $data = json_decode($response['body'], true);
                $post = $data[0]['data']['children'][0]['data'] ?? null;
                if ($post && !empty($post['url']) && str_contains($post['url'], 'v.redd.it')) {
                    $links[] = [
                        'type' => 'info',
                        'label' => 'Reddit-hosted video detected',
                        'url' => $post['url'],
                        'quality' => null,
                        'download' => false,
                    ];
                }
            }
        }

        return $links;
    }

    private function toJsonUrl(string $url): ?string
    {
        if (preg_match('#v\.redd\.it/#', $url)) {
            return null;
        }

        $parsed = parse_url($url);
        if ($parsed === false) {
            return null;
        }

        $path = rtrim($parsed['path'] ?? '', '/');
        if ($path === '') {
            return null;
        }

        return 'https://www.reddit.com' . $path . '.json?raw_json=1';
    }
}
