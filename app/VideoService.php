<?php

declare(strict_types=1);

namespace App;

use App\Repositories\DownloadSessionRepository;
use App\Storage\DatabaseConnection;

class VideoService
{
    private const CACHE_VERSION = 'v2';

    private PlatformDetector $detector;
    private Cache $cache;
    private ProviderRegistry $videoRegistry;
    private ProviderRegistry $audioRegistry;
    private Analytics $analytics;
    private StorageInterface $db;
    private DownloadSessionRepository $sessions;
    private Settings $settings;

    public function __construct(
        PlatformDetector $detector,
        Cache $cache,
        ProviderRegistry $videoRegistry,
        ProviderRegistry $audioRegistry,
        Analytics $analytics,
        StorageInterface $db,
        Settings $settings
    ) {
        $this->detector = $detector;
        $this->cache = $cache;
        $this->videoRegistry = $videoRegistry;
        $this->audioRegistry = $audioRegistry;
        $this->analytics = $analytics;
        $this->db = $db;
        $this->sessions = new DownloadSessionRepository(DatabaseConnection::get());
        $this->settings = $settings;
    }

    public function process(string $url, string $serviceId = ServiceConfig::SERVICE_ALL): array
    {
        if ($serviceId === ServiceConfig::SERVICE_ALL) {
            return $this->processAll($url);
        }

        return $this->processForService($url, $serviceId);
    }

    private function processAll(string $url): array
    {
        $normalized = $this->detector->normalize($url);
        $platform = $this->detector->detect($normalized);

        if ($platform === 'unknown') {
            return [
                'success' => false,
                'error' => 'unsupported_platform',
                'message' => 'Unsupported platform. Please use a URL from one of the supported providers.',
            ];
        }

        $available = ServiceConfig::availableServicesForPlatform($this->settings, $platform);
        if ($available === []) {
            return [
                'success' => false,
                'error' => 'platform_unavailable',
                'message' => 'This platform is not available for download on this site.',
            ];
        }

        if (count($available) === 1) {
            return $this->processForService($url, $available[0]);
        }

        $sections = [];
        $flatLinks = [];
        $baseData = null;

        foreach ($available as $serviceId) {
            $result = $this->processForService($url, $serviceId);
            if (!$result['success']) {
                continue;
            }

            $data = $result['data'];
            $sectionLinks = [];
            foreach ($data['links'] ?? [] as $link) {
                $entry = array_merge($link, [
                    'service' => $serviceId,
                    'service_type' => ServiceConfig::serviceType($serviceId),
                ]);
                $sectionLinks[] = $entry;
                $flatLinks[] = $entry;
            }

            if ($sectionLinks === []) {
                continue;
            }

            $sections[] = [
                'service' => $serviceId,
                'service_type' => ServiceConfig::serviceType($serviceId),
                'label' => ServiceConfig::serviceLabel($serviceId, $this->settings),
                'links' => $sectionLinks,
            ];

            if ($baseData === null) {
                $baseData = $data;
            }
        }

        if ($sections === []) {
            return [
                'success' => false,
                'error' => 'fetch_failed',
                'message' => 'Unable to retrieve download links. Please try again later.',
            ];
        }

        if (count($sections) === 1) {
            $single = $sections[0];
            $data = $baseData ?? [];
            $data['links'] = $single['links'];
            $data['service'] = $single['service'];
            $data['service_type'] = $single['service_type'];

            return ['success' => true, 'data' => $data, 'cached' => false];
        }

        $combined = [
            'platform' => $platform,
            'platform_name' => $baseData['platform_name'] ?? ucfirst($platform),
            'title' => $baseData['title'] ?? '',
            'author' => $baseData['author'] ?? '',
            'duration' => $baseData['duration'] ?? null,
            'thumbnail' => $baseData['thumbnail'] ?? '',
            'notice' => $baseData['notice'] ?? '',
            'service' => ServiceConfig::SERVICE_ALL,
            'service_type' => 'all',
            'combined' => true,
            'sections' => $sections,
            'links' => $flatLinks,
            'normalized_url' => $normalized,
            'original_url' => $url,
            'cache_key' => hash('sha256', ServiceConfig::SERVICE_ALL . '|' . $normalized),
        ];

        return ['success' => true, 'data' => $combined, 'cached' => false];
    }

    public function processForService(string $url, string $serviceId): array
    {
        $start = microtime(true);
        $serviceType = ServiceConfig::serviceType($serviceId);
        $registry = $serviceId === ServiceConfig::SERVICE_AUDIO ? $this->audioRegistry : $this->videoRegistry;

        $normalized = $this->detector->normalize($url);
        $platform = $this->detector->detect($normalized);

        if ($platform === 'unknown') {
            $this->analytics->record('unknown', false, (microtime(true) - $start) * 1000);
            return [
                'success' => false,
                'error' => 'unsupported_platform',
                'message' => 'Unsupported platform for this service. Please use a URL from one of the supported providers.',
            ];
        }

        if (!ServiceConfig::isProviderAssigned($this->settings, $serviceId, $platform)) {
            $this->analytics->record($platform, false, (microtime(true) - $start) * 1000);
            return [
                'success' => false,
                'error' => 'platform_not_in_service',
                'message' => 'This platform is not available under the selected service.',
            ];
        }

        if (!PlatformConfig::isEnabled($this->settings, $platform, $serviceType)) {
            $this->analytics->record($platform, false, (microtime(true) - $start) * 1000);
            return [
                'success' => false,
                'error' => 'platform_disabled',
                'message' => 'This platform is currently unavailable.',
            ];
        }

        $cacheLookup = self::CACHE_VERSION . '|' . $serviceId . '|' . $normalized;
        $cached = $this->cache->get($cacheLookup);
        if ($cached !== null) {
            if ($serviceType === 'audio') {
                $cached['links'] = $this->filterAudioLinks($cached['links'] ?? []);
                if (($cached['links'] ?? []) === []) {
                    $cached = null;
                }
            }
        }
        if ($cached !== null) {
            if (ChannelBlocker::isBlocked($this->settings, $platform, $cached, $serviceType)) {
                $this->analytics->record($platform, false, (microtime(true) - $start) * 1000);
                return [
                    'success' => false,
                    'error' => 'channel_blocked',
                    'message' => 'This content is not available for download on this site.',
                ];
            }
            $this->analytics->record($platform, true, (microtime(true) - $start) * 1000);
            return ['success' => true, 'data' => $cached, 'cached' => true];
        }

        $provider = $registry->get($platform);
        if ($provider === null) {
            $this->analytics->record($platform, false, (microtime(true) - $start) * 1000);
            return [
                'success' => false,
                'error' => 'provider_unavailable',
                'message' => 'Platform temporarily unavailable for this service.',
            ];
        }

        try {
            $result = $provider->fetch($normalized);
        } catch (\Throwable $e) {
            Logger::error("Provider error [{$platform}/{$serviceId}]: " . $e->getMessage());
            $this->analytics->record($platform, false, (microtime(true) - $start) * 1000);
            return [
                'success' => false,
                'error' => 'fetch_failed',
                'message' => 'Unable to retrieve information. Please try again later.',
            ];
        }

        if (!$result['success']) {
            $this->analytics->record($platform, false, (microtime(true) - $start) * 1000);
            return $result;
        }

        $data = $result['data'];
        if (ChannelBlocker::isBlocked($this->settings, $platform, $data, $serviceType)) {
            $this->analytics->record($platform, false, (microtime(true) - $start) * 1000);
            return [
                'success' => false,
                'error' => 'channel_blocked',
                'message' => 'This content is not available for download on this site.',
            ];
        }

        if ($serviceType === 'audio') {
            $data['links'] = $this->filterAudioLinks($data['links'] ?? []);
        }

        $data['platform'] = $platform;
        $data['service'] = $serviceId;
        $data['service_type'] = $serviceType;
        $data['normalized_url'] = $normalized;
        $data['original_url'] = $url;
        $data['cache_key'] = hash('sha256', $cacheLookup);

        foreach ($data['links'] ?? [] as $i => $link) {
            $data['links'][$i]['service'] = $serviceId;
            $data['links'][$i]['service_type'] = $serviceType;
        }

        if (($data['links'] ?? []) !== []) {
            $this->cache->set($cacheLookup, $data);
        }
        $this->analytics->record($platform, true, (microtime(true) - $start) * 1000);

        return ['success' => true, 'data' => $data, 'cached' => false];
    }

    /** @param array<int, array<string, mixed>> $links @return array<int, array<string, mixed>> */
    private function filterAudioLinks(array $links): array
    {
        return array_values(array_filter($links, static function (array $link): bool {
            return strtolower((string) ($link['ext'] ?? '')) === 'mp3';
        }));
    }

    public function storeResult(array $data): string
    {
        $token = Security::generateToken(16);
        $ttl = 3600;
        $this->sessions->store($token, $data, $ttl);
        return $token;
    }

    public function getResult(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }

        return $this->sessions->get($token);
    }

    public function cleanupResult(string $token): bool
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return false;
        }

        if (!$this->sessions->exists($token)) {
            return true;
        }

        $data = $this->sessions->take($token) ?? [];

        $cacheKey = $data['cache_key'] ?? null;
        if (is_string($cacheKey) && $cacheKey !== '') {
            $cacheFile = $this->cache->pathForKey($cacheKey);
            if (is_file($cacheFile)) {
                @unlink($cacheFile);
            }
        }

        if (!empty($data['combined']) && !empty($data['normalized_url'])) {
            foreach ([ServiceConfig::SERVICE_ALL, ServiceConfig::SERVICE_VIDEO, ServiceConfig::SERVICE_AUDIO] as $serviceId) {
                $this->cache->delete($serviceId . '|' . (string) $data['normalized_url']);
            }
        } elseif (!empty($data['service']) && !empty($data['normalized_url'])) {
            $this->cache->delete((string) $data['service'] . '|' . (string) $data['normalized_url']);
        }

        foreach (['normalized_url', 'original_url'] as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                $this->cache->delete($data[$key]);
            }
        }

        return true;
    }
}
