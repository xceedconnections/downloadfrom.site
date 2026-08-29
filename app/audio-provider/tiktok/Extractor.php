<?php

declare(strict_types=1);

namespace App\AudioProvider\Tiktok;

use App\HttpClient;
use App\YtDlpProvider;

require_once __DIR__ . '/tiktokDlp.php';

class Extractor
{
    /** @var array<string, mixed> */
    private array $config;

    private TiktokDlp $dlp;

    /** @param array<string, mixed> $config */
    public function __construct(HttpClient $http, array $config = [])
    {
        $this->config = $config;
        $this->dlp = new TiktokDlp($config);
    }

    public function extract(string $url): array
    {
        return YtDlpProvider::extractAudioMp3($url, $this->config);
    }
}
