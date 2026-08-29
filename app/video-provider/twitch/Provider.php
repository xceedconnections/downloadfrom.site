<?php

declare(strict_types=1);

namespace App\Provider\Twitch;

class Provider extends \App\Provider\AbstractProvider
{
public function getMetadata(string $url): array
    {
        $type = $this->detectType($url);

        return [
            'success' => true,
            'data' => [
                'title' => $type === 'clip' ? 'Twitch Clip' : 'Twitch Video',
                'author' => $this->extractChannel($url),
                'thumbnail' => null,
                'duration' => null,
                'platform_name' => 'Twitch',
                'content_type' => $type,
            ],
        ];
    }

    public function getAvailableLinks(string $url): array
    {
        return [$this->watchLink($url, 'View on Twitch')];
    }

    private function detectType(string $url): string
    {
        if (str_contains($url, 'clips.twitch.tv') || str_contains($url, '/clip/')) {
            return 'clip';
        }
        if (str_contains($url, '/videos/')) {
            return 'vod';
        }
        return 'stream';
    }

    private function extractChannel(string $url): ?string
    {
        if (preg_match('#twitch\.tv/([^/?]+)#', $url, $m) && !in_array($m[1], ['videos', 'directory', 'settings'], true)) {
            return $m[1];
        }
        return null;
    }
}
