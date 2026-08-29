<?php

declare(strict_types=1);

namespace App\Provider\Snapchat;

class Provider extends \App\Provider\AbstractProvider
{
public function getMetadata(string $url): array
    {
        return [
            'success' => true,
            'data' => [
                'title' => 'Snapchat Content',
                'author' => null,
                'thumbnail' => null,
                'duration' => null,
                'platform_name' => 'Snapchat',
            ],
        ];
    }

    public function getAvailableLinks(string $url): array
    {
        return [$this->watchLink($url, 'Open in Snapchat')];
    }
}
