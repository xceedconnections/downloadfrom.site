<?php

declare(strict_types=1);

namespace App\Provider\Dailymotion;

class Provider extends \App\Provider\AbstractProvider
{
public function getMetadata(string $url): array
    {
        $result = $this->fetchOembed('https://www.dailymotion.com/services/oembed', $url);
        if (!$result['success']) {
            return $result;
        }

        $raw = $result['raw'];

        return [
            'success' => true,
            'data' => [
                'title' => $raw['title'] ?? 'Dailymotion Video',
                'author' => $raw['author_name'] ?? null,
                'thumbnail' => $raw['thumbnail_url'] ?? null,
                'duration' => null,
                'platform_name' => 'Dailymotion',
            ],
        ];
    }

    public function getAvailableLinks(string $url): array
    {
        return [$this->watchLink($url, 'Watch on Dailymotion')];
    }
}
