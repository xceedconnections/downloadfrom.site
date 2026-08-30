<?php

declare(strict_types=1);

namespace App\Provider\Youtube;

use App\HttpClient;
use App\YoutubeInnertube;

require_once __DIR__ . '/youtubeDlp.php';

class Extractor
{
    private YoutubeDlp $ytdlp;
    private YoutubeInnertube $innertube;

    /** @param array<string, mixed> $config */
    public function __construct(HttpClient $http, array $config = [])
    {
        $this->ytdlp = new YoutubeDlp($config);
        $this->innertube = new YoutubeInnertube($http);
    }

    public function extract(string $url): array
    {
        $links = $this->ytdlp->extract($url);
        if ($links !== []) {
            return $links;
        }

        return $this->innertube->extractVideoLinks($url);
    }
}
