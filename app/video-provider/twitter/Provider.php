<?php

declare(strict_types=1);

namespace App\Provider\Twitter;

class Provider extends \App\Provider\AbstractProvider
{
public function getMetadata(string $url): array
    {
        $result = $this->fetchOembed('https://publish.twitter.com/oembed', $url);
        if (!$result['success']) {
            return [
                'success' => true,
                'data' => [
                    'title' => 'X / Twitter Post',
                    'author' => $this->extractUsername($url),
                    'thumbnail' => null,
                    'duration' => null,
                    'platform_name' => 'X (Twitter)',
                ],
            ];
        }

        $raw = $result['raw'];

        return [
            'success' => true,
            'data' => [
                'title' => $raw['author_name'] ?? 'X / Twitter Post',
                'author' => $raw['author_name'] ?? $this->extractUsername($url),
                'thumbnail' => null,
                'duration' => null,
                'platform_name' => 'X (Twitter)',
            ],
        ];
    }

    public function getAvailableLinks(string $url): array
    {
        return [$this->watchLink($url, 'View on X')];
    }

    private function extractUsername(string $url): ?string
    {
        if (preg_match('#(?:twitter|x)\.com/([^/]+)/#', $url, $m)) {
            return '@' . $m[1];
        }
        return null;
    }
}
