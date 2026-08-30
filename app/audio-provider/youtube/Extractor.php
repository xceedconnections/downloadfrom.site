<?php

declare(strict_types=1);

namespace App\AudioProvider\Youtube;

use App\HttpClient;
use App\YtDlpFormatLinks;
use App\YoutubeInnertube;

require_once __DIR__ . '/youtubeDlp.php';

class Extractor
{
    private HttpClient $http;
    private YoutubeDlp $dlp;
    private YoutubeInnertube $innertube;

    /** @param array<string, mixed> $config */
    public function __construct(HttpClient $http, array $config = [])
    {
        $this->http = $http;
        $this->dlp = new YoutubeDlp($config);
        $this->innertube = new YoutubeInnertube($http);
    }

    public function extract(string $url): array
    {
        $links = $this->dlp->extract($url);
        $fallback = $this->innertube->extractAudioLinks($url);

        return YtDlpFormatLinks::mergeDownloadLinks($links, $fallback);
    }
}
