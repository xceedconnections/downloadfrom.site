<?php

declare(strict_types=1);

namespace App\AudioProvider\Youtube;

class Provider extends \App\Provider\AbstractProvider
{
    public function getMetadata(string $url): array
    {
        $result = $this->fetchOembed('https://www.youtube.com/oembed', $url);
        if (!$result['success']) {
            return $result;
        }

        $raw = $result['raw'];
        $videoId = $this->extractVideoId($url);

        return [
            'success' => true,
            'data' => [
                'title' => $raw['title'] ?? 'YouTube Audio',
                'author' => $raw['author_name'] ?? null,
                'thumbnail' => $raw['thumbnail_url'] ?? ($videoId ? "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg" : null),
                'duration' => null,
                'platform_name' => 'YouTube',
            ],
        ];
    }

    public function getAvailableLinks(string $url): array
    {
        return [];
    }

    private function extractVideoId(string $url): ?string
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
