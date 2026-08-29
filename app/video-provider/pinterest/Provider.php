<?php

declare(strict_types=1);

namespace App\Provider\Pinterest;

class Provider extends \App\Provider\AbstractProvider
{
public function getMetadata(string $url): array
    {
        return [
            'success' => true,
            'data' => [
                'title' => 'Pinterest Pin',
                'author' => null,
                'thumbnail' => null,
                'duration' => null,
                'platform_name' => 'Pinterest',
            ],
        ];
    }

    public function getAvailableLinks(string $url): array
    {
        return [$this->watchLink($url, 'View on Pinterest')];
    }
}
