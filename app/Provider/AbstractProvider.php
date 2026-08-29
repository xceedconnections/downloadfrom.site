<?php

declare(strict_types=1);

namespace App\Provider;

use App\HttpClient;
use App\PlatformDetector;

abstract class AbstractProvider implements VideoProviderInterface
{
    protected HttpClient $http;
    protected PlatformDetector $detector;
    protected array $manifest;
    protected ?object $downloadExtractor = null;
    protected ?array $lastData = null;

    public function __construct(HttpClient $http, PlatformDetector $detector, array $manifest)
    {
        $this->http = $http;
        $this->detector = $detector;
        $this->manifest = $manifest;
    }

    public function setDownloadExtractor(?object $extractor): void
    {
        $this->downloadExtractor = $extractor;
    }

    public function getManifest(): array
    {
        return $this->manifest;
    }

    public function getPlatform(): string
    {
        return $this->manifest['id'];
    }

    public function detect(string $url): bool
    {
        return $this->detector->detect($url) === $this->getPlatform();
    }

    public function fetch(string $url): array
    {
        $metadata = $this->getMetadata($url);
        if (!$metadata['success']) {
            return $metadata;
        }

        $this->lastData = $metadata['data'];
        $platformLinks = $this->getAvailableLinks($url);
        $downloadLinks = $this->extractDownloads($url);

        if (count($downloadLinks) > 0) {
            $links = $downloadLinks;
        } elseif (!empty($this->manifest['downloads_only'])) {
            $links = [];
        } else {
            $links = $platformLinks;
        }

        $data = $metadata['data'];
        unset($data['notice']);

        if (count($downloadLinks) > 0) {
            $data['notice'] = null;
        } elseif ($links === []) {
            $data['notice'] = 'Could not extract a direct download link. The video may be private or protected.';
        }

        return [
            'success' => true,
            'data' => array_merge($data, ['links' => $links]),
        ];
    }

    public function getThumbnail(): ?string
    {
        return $this->lastData['thumbnail'] ?? null;
    }

    public function getTitle(): ?string
    {
        return $this->lastData['title'] ?? null;
    }

    public function getDuration(): ?int
    {
        return isset($this->lastData['duration']) ? (int) $this->lastData['duration'] : null;
    }

    public function getAuthor(): ?string
    {
        return $this->lastData['author'] ?? null;
    }

    protected function extractDownloads(string $url): array
    {
        if ($this->downloadExtractor !== null && method_exists($this->downloadExtractor, 'extract')) {
            return $this->downloadExtractor->extract($url);
        }
        return [];
    }

    protected function fetchOembed(string $endpoint, string $url): array
    {
        $oembedUrl = $endpoint . '?url=' . urlencode($url) . '&format=json';
        $response = $this->http->get($oembedUrl);

        if (!$response['success'] || empty($response['body'])) {
            return [
                'success' => false,
                'error' => 'fetch_failed',
                'message' => 'Unable to retrieve information from this platform.',
            ];
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            return [
                'success' => false,
                'error' => 'parse_failed',
                'message' => 'Unable to parse platform response.',
            ];
        }

        return ['success' => true, 'raw' => $data];
    }

    protected function watchLink(string $url, string $label = 'Watch on Platform'): array
    {
        return [
            'type' => 'link',
            'label' => $label,
            'url' => $url,
            'quality' => null,
            'download' => false,
        ];
    }
}
