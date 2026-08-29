<?php

declare(strict_types=1);

namespace App\Provider\Twitch;

require_once __DIR__ . '/twitchDlp.php';
use App\HttpClient;

class Extractor
{
    private TwitchDlp $ytdlp;

    public function __construct(HttpClient $http, array $config = [])
    {
        $this->ytdlp = new TwitchDlp($config);
    }

    public function extract(string $url): array
    {
        return $this->ytdlp->extract($url);
    }
}
