<?php

declare(strict_types=1);

namespace App\Provider\Tiktok;

class Provider extends \App\Provider\AbstractProvider
{
    public function getMetadata(string $url): array
    {
        $result = $this->fetchOembed('https://www.tiktok.com/oembed', $url);
        if ($result['success']) {
            $raw = $result['raw'];

            return [
                'success' => true,
                'data' => [
                    'title' => $raw['title'] ?? 'TikTok Video',
                    'author' => $raw['author_name'] ?? $this->extractUsername($url),
                    'thumbnail' => $raw['thumbnail_url'] ?? null,
                    'duration' => null,
                    'platform_name' => 'TikTok',
                ],
            ];
        }

        return [
            'success' => true,
            'data' => [
                'title' => 'TikTok Video',
                'author' => $this->extractUsername($url),
                'thumbnail' => null,
                'duration' => null,
                'platform_name' => 'TikTok',
            ],
        ];
    }

    public function getAvailableLinks(string $url): array
    {
        return [];
    }

    private function extractUsername(string $url): ?string
    {
        if (preg_match('#tiktok\.com/@([^/]+)#', $url, $m)) {
            return '@' . $m[1];
        }

        return null;
    }
}
