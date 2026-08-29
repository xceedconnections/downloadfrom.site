<?php

declare(strict_types=1);

namespace App\Provider\Linkedin;

require_once __DIR__ . '/linkedinDlp.php';
use App\HttpClient;

class Extractor
{
    private LinkedinDlp $ytdlp;

    public function __construct(HttpClient $http, array $config = [])
    {
        $this->ytdlp = new LinkedinDlp($config);
    }

    public function extract(string $url): array
    {
        return $this->ytdlp->extract($url);
    }
}
