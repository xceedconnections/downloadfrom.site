<?php

declare(strict_types=1);

namespace App\AudioProvider\Soundcloud;

class Provider extends \App\Provider\AbstractProvider
{
    public function getMetadata(string $url): array
    {
        return [
            'success' => true,
            'data' => [
                'title' => 'SoundCloud Audio',
                'author' => null,
                'thumbnail' => null,
                'duration' => null,
                'platform_name' => 'SoundCloud',
            ],
        ];
    }

    public function getAvailableLinks(string $url): array
    {
        return [];
    }
}
