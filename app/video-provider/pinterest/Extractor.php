<?php

declare(strict_types=1);

namespace App\Provider\Pinterest;

require_once __DIR__ . '/pinterestDlp.php';
use App\HttpClient;

class Extractor
{
    private PinterestDlp $ytdlp;

    public function __construct(HttpClient $http, array $config = [])
    {
        $this->ytdlp = new PinterestDlp($config);
    }

    public function extract(string $url): array
    {
        return $this->ytdlp->extract($url);
    }
}
