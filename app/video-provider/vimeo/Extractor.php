<?php

declare(strict_types=1);

namespace App\Provider\Vimeo;

require_once __DIR__ . '/vimeoDlp.php';
use App\HttpClient;

class Extractor
{
    private HttpClient $http;
    private VimeoDlp $ytdlp;

    public function __construct(HttpClient $http, array $config = [])
    {
        $this->http = $http;
        $this->ytdlp = new VimeoDlp($config);
    }

    public function extract(string $url): array
    {
        $links = $this->ytdlp->extract($url);
        if ($links !== []) {
            return $links;
        }

        return $this->extractViaConfigApi($url);
    }

    private function extractViaConfigApi(string $url): array
    {
        if (!preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            return [];
        }

        $response = $this->http->get(
            "https://player.vimeo.com/video/{$m[1]}/config",
            ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36']
        );

        if (!$response['success'] || empty($response['body'])) {
            return [];
        }

        $data = json_decode($response['body'], true);
        $files = $data['request']['files']['progressive'] ?? [];

        usort($files, static fn(array $a, array $b): int => ($b['height'] ?? 0) <=> ($a['height'] ?? 0));

        $links = [];
        foreach ($files as $file) {
            if (empty($file['url'])) {
                continue;
            }
            $height = (int) ($file['height'] ?? 0);
            $links[] = [
                'type' => 'download',
                'label' => $height > 0 ? "{$height}p MP4" : 'MP4',
                'url' => $file['url'],
                'quality' => "{$height}p",
                'download' => true,
                'ext' => 'mp4',
            ];
        }

        return $links;
    }
}
