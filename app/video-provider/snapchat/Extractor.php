<?php

declare(strict_types=1);

namespace App\Provider\Snapchat;

require_once __DIR__ . '/snapchatDlp.php';
use App\HttpClient;

class Extractor
{
    private SnapchatDlp $ytdlp;

    public function __construct(HttpClient $http, array $config = [])
    {
        $this->ytdlp = new SnapchatDlp($config);
    }

    public function extract(string $url): array
    {
        return $this->ytdlp->extract($url);
    }
}
