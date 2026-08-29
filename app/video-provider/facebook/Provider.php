<?php

declare(strict_types=1);

namespace App\Provider\Facebook;

class Provider extends \App\Provider\AbstractProvider
{
public function getMetadata(string $url): array
    {
        return [
            'success' => true,
            'data' => [
                'title' => 'Facebook Video',
                'author' => null,
                'thumbnail' => null,
                'duration' => null,
                'platform_name' => 'Facebook',
            ],
        ];
    }

    public function getAvailableLinks(string $url): array
    {
        return [$this->watchLink($url, 'View on Facebook')];
    }
}
