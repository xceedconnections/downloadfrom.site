<?php

declare(strict_types=1);

namespace App\AudioProvider\Tiktok;

class Provider extends \App\Provider\AbstractProvider
{
    public function getMetadata(string $url): array
    {
        return [
            'success' => true,
            'data' => [
                'title' => 'TikTok Audio',
                'author' => null,
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
}
