<?php

declare(strict_types=1);

namespace App\Provider\Vimeo;

class Provider extends \App\Provider\AbstractProvider
{
public function getMetadata(string $url): array
    {
        $result = $this->fetchOembed('https://vimeo.com/api/oembed.json', $url);
        if (!$result['success']) {
            return $result;
        }

        $raw = $result['raw'];
        $duration = isset($raw['duration']) ? (int) $raw['duration'] : null;

        return [
            'success' => true,
            'data' => [
                'title' => $raw['title'] ?? 'Vimeo Video',
                'author' => $raw['author_name'] ?? null,
                'thumbnail' => $raw['thumbnail_url'] ?? null,
                'duration' => $duration,
                'platform_name' => 'Vimeo',
            ],
        ];
    }

    public function getAvailableLinks(string $url): array
    {
        $videoId = $this->extractVideoId($url);
        $links = [$this->watchLink($url, 'Watch on Vimeo')];

        if ($videoId) {
            $links[] = [
                'type' => 'embed',
                'label' => 'Embed',
                'url' => "https://player.vimeo.com/video/{$videoId}",
                'quality' => null,
                'download' => false,
            ];
        }

        return $links;
    }

    private function extractVideoId(string $url): ?string
    {
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            return $m[1];
        }
        return null;
    }
}
