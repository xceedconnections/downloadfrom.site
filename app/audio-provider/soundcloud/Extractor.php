<?php

declare(strict_types=1);

namespace App\AudioProvider\Soundcloud;

use App\HttpClient;
use App\YtDlpProvider;

require_once __DIR__ . '/soundcloudDlp.php';

class Extractor
{
    /** @var array<string, mixed> */
    private array $config;

    private SoundcloudDlp $dlp;

    /** @param array<string, mixed> $config */
    public function __construct(HttpClient $http, array $config = [])
    {
        $this->config = $config;
        $this->dlp = new SoundcloudDlp($config);
    }

    public function extract(string $url): array
    {
        return YtDlpProvider::extractAudioMp3($url, $this->config);
    }
}
