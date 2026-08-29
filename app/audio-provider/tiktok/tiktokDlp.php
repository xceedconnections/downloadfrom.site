<?php

declare(strict_types=1);

namespace App\AudioProvider\Tiktok;

use App\YtDlpFormatLinks;
use App\YtDlpHelper;
use App\YtDlpProvider;

class TiktokDlp
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config = [])
    {
    }

    public function isAvailable(): bool
    {
        return YtDlpHelper::resolvePath($this->config) !== null;
    }

    public function extract(string $url): array
    {
        return YtDlpProvider::extractAudioMp3($url, $this->config);
    }

    /** @param array<int, array<string, mixed>> $formats */
    public function buildAudioLinksFromFormats(array $formats): array
    {
        return YtDlpFormatLinks::buildAudioMp3Links($formats);
    }

    /** @param array<int, array<string, mixed>> $formats */
    public function buildLinksFromFormats(array $formats): array
    {
        return $this->buildAudioLinksFromFormats($formats);
    }
}
