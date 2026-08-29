<?php

declare(strict_types=1);

namespace App\Provider\Instagram;

class Provider extends \App\Provider\AbstractProvider
{
public function getMetadata(string $url): array
    {
        return [
            'success' => true,
            'data' => [
                'title' => 'Instagram Content',
                'author' => $this->extractUsername($url),
                'thumbnail' => null,
                'duration' => null,
                'platform_name' => 'Instagram',
            ],
        ];
    }

    public function getAvailableLinks(string $url): array
    {
        return [$this->watchLink($url, 'View on Instagram')];
    }

    private function extractUsername(?string $url): ?string
    {
        if ($url && preg_match('#instagram\.com/([^/]+)/#', $url, $m)) {
            return '@' . $m[1];
        }
        return null;
    }
}
