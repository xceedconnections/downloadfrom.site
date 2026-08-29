<?php

declare(strict_types=1);

namespace App\Provider\Snapchat;

use App\YtDlpFormatLinks;
use App\YtDlpHelper;
use App\YtDlpProvider;

class SnapchatDlp
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
        return YtDlpProvider::extractVideo($url, $this->config);
    }

    /** @param array<int, array<string, mixed>> $formats */
    public function buildLinksFromFormats(array $formats): array
    {
        return YtDlpFormatLinks::buildVideoLinks($formats);
    }
}
