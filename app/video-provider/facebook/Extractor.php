<?php

declare(strict_types=1);

namespace App\Provider\Facebook;

require_once __DIR__ . '/facebookDlp.php';
use App\HttpClient;

class Extractor
{
    private HttpClient $http;
    private FacebookDlp $ytdlp;

    public function __construct(HttpClient $http, array $config = [])
    {
        $this->http = $http;
        $this->ytdlp = new FacebookDlp($config);
    }

    public function extract(string $url): array
    {
        $links = $this->ytdlp->extract($url);
        if ($links !== []) {
            return $links;
        }

        return $this->extractFromPage($url);
    }

    private function extractFromPage(string $url): array
    {
        $response = $this->http->get($url, ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36']);
        if (!$response['success'] || empty($response['body'])) {
            return [];
        }

        $links = [];
        if (preg_match_all('#https://[^"\']+\.fbcdn\.net/[^"\']+\.mp4[^"\']*#', $response['body'], $matches)) {
            $urls = array_unique($matches[0]);
            foreach (array_slice($urls, 0, 3) as $i => $mediaUrl) {
                $links[] = [
                    'type' => 'download',
                    'label' => 'Facebook Video MP4' . (count($urls) > 1 ? ' #' . ($i + 1) : ''),
                    'url' => html_entity_decode(str_replace('\\/', '/', $mediaUrl)),
                    'quality' => 'MP4',
                    'download' => true,
                    'ext' => 'mp4',
                ];
            }
        }

        return $links;
    }
}
