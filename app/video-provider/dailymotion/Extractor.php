<?php

declare(strict_types=1);

namespace App\Provider\Dailymotion;

require_once __DIR__ . '/dailymotionDlp.php';
use App\HttpClient;

class Extractor
{
    private HttpClient $http;
    private DailymotionDlp $ytdlp;

    public function __construct(HttpClient $http, array $config = [])
    {
        $this->http = $http;
        $this->ytdlp = new DailymotionDlp($config);
    }

    public function extract(string $url): array
    {
        $links = $this->ytdlp->extract($url);
        if ($links !== []) {
            return $links;
        }

        return $this->extractViaMetadataApi($url);
    }

    private function extractViaMetadataApi(string $url): array
    {
        $videoId = null;
        if (preg_match('#dailymotion\.com/video/([a-zA-Z0-9]+)#', $url, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('#dai\.ly/([a-zA-Z0-9]+)#', $url, $m)) {
            $videoId = $m[1];
        }

        if ($videoId === null) {
            return [];
        }

        $response = $this->http->get(
            "https://www.dailymotion.com/player/metadata/video/{$videoId}",
            ['User-Agent: Mozilla/5.0']
        );

        if (!$response['success'] || empty($response['body'])) {
            return [];
        }

        $data = json_decode($response['body'], true);
        $qualities = $data['qualities'] ?? [];
        $links = [];

        foreach ($qualities as $quality => $info) {
            if (empty($info['url'])) {
                continue;
            }
            $links[] = [
                'type' => 'download',
                'label' => strtoupper((string) $quality) . ' MP4',
                'url' => $info['url'],
                'quality' => (string) $quality,
                'download' => true,
                'ext' => 'mp4',
            ];
        }

        usort($links, static fn(array $a, array $b): int => strcmp($b['quality'], $a['quality']));

        return $links;
    }
}
