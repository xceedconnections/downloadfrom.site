<?php

declare(strict_types=1);

namespace App\Provider\Twitter;

require_once __DIR__ . '/twitterDlp.php';
use App\HttpClient;

class Extractor
{
    private HttpClient $http;
    private TwitterDlp $ytdlp;

    public function __construct(HttpClient $http, array $config = [])
    {
        $this->http = $http;
        $this->ytdlp = new TwitterDlp($config);
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
        $pageResponse = $this->http->get(
            $url,
            ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36']
        );

        if (!$pageResponse['success'] || empty($pageResponse['body'])) {
            return [];
        }

        $links = [];
        if (preg_match_all('#https://video\.twimg\.com/[^"\']+\.mp4#', $pageResponse['body'], $matches)) {
            $urls = array_unique($matches[0]);
            foreach ($urls as $i => $mediaUrl) {
                $links[] = [
                    'type' => 'download',
                    'label' => 'Video MP4' . (count($urls) > 1 ? ' #' . ($i + 1) : ''),
                    'url' => html_entity_decode($mediaUrl),
                    'quality' => 'MP4',
                    'download' => true,
                    'ext' => 'mp4',
                ];
            }
        }

        return $links;
    }
}
