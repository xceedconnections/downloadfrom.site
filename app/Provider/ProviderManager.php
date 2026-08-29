<?php

declare(strict_types=1);

namespace App\Provider;

use App\HttpClient;
use App\PlatformDetector;
use App\ProviderRegistry;
use App\Logger;

/**
 * Loads video providers (app/video-provider/) and audio providers (app/audio-provider/).
 */
class ProviderManager
{
    private static ?self $instance = null;

    private ProviderLoader $videoLoader;
    private ProviderLoader $audioLoader;
    private ProviderRegistry $videoRegistry;
    private ProviderRegistry $audioRegistry;
    /** @var array<string, array> */
    private array $videoPlatforms = [];
    /** @var array<string, array> */
    private array $audioPlatforms = [];
    /** @var array<string, array> */
    private array $videoPlugins = [];
    /** @var array<string, array> */
    private array $audioPlugins = [];
    private PlatformDetector $detector;
    private HttpClient $http;

    private function __construct(private array $config)
    {
        $appRoot = dirname(__DIR__);
        $this->videoLoader = new ProviderLoader($appRoot . '/video-provider', 'App\\Provider');
        $this->audioLoader = new ProviderLoader($appRoot . '/audio-provider', 'App\\AudioProvider');
        $this->videoRegistry = new ProviderRegistry();
        $this->audioRegistry = new ProviderRegistry();
        $this->detector = new PlatformDetector();
        $this->loadAllPlugins();
    }

    public static function boot(array $config): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('ProviderManager not booted. Call ProviderManager::boot() first.');
        }

        return self::$instance;
    }

    private function loadAllPlugins(): void
    {
        $allowedDomains = [
            'googlevideo.com', 'i.ytimg.com', 's.ytimg.com',
            'tiktok.com', 'vm.tiktok.com', 'vt.tiktok.com', 'tiktokcdn.com', 'tiktokv.com',
            'tiktokcdn-us.com', 'muscdn.com', 'tikwm.com',
            'vimeocdn.com', 'vimeo.com', 'player.vimeo.com',
            'dailymotion.com', 'dmcdn.net', 'dai.ly',
            'fbcdn.net', 'cdninstagram.com', 'instagram.com',
            'video.twimg.com', 'twimg.com', 'twitter.com', 'x.com',
            'v.redd.it', 'reddit.com', 'i.redd.it',
            'twitch.tv', 'clips.twitch.tv',
            'pinterest.com', 'pinimg.com',
            'soundcloud.com', 'sndcdn.com', 'scdn.co',
        ];
        $hostPatterns = [];
        $normalizers = [];

        foreach ([$this->videoLoader->discover(), $this->audioLoader->discover()] as $discovered) {
            foreach ($discovered as $entry) {
                $manifest = $entry['manifest'];
                foreach ($manifest['allowed_domains'] ?? $manifest['supported_domains'] ?? [] as $domain) {
                    $allowedDomains[] = $domain;
                }
                if (!empty($manifest['host_patterns'])) {
                    $hostPatterns[$manifest['id']] = $manifest['host_patterns'];
                }
                if (!empty($manifest['url_normalizer']) && is_callable($manifest['url_normalizer'])) {
                    $normalizers[$manifest['id']] = $manifest['url_normalizer'];
                }
            }
        }

        $this->http = new HttpClient(array_unique($allowedDomains), 25);
        $this->detector->setPatterns($hostPatterns);
        $this->detector->setNormalizers($normalizers);

        $this->loadPluginsFrom(
            $this->videoLoader,
            $this->videoRegistry,
            $this->videoPlatforms,
            $this->videoPlugins,
            'video'
        );
        $this->loadPluginsFrom(
            $this->audioLoader,
            $this->audioRegistry,
            $this->audioPlatforms,
            $this->audioPlugins,
            'audio'
        );
    }

    /**
     * @param array<string, array> $platformsOut
     * @param array<string, array> $pluginsOut
     */
    private function loadPluginsFrom(
        ProviderLoader $loader,
        ProviderRegistry $registry,
        array &$platformsOut,
        array &$pluginsOut,
        string $serviceType
    ): void {
        foreach ($loader->discover() as $entry) {
            $manifest = $entry['manifest'];
            $dir = $entry['dir'];
            $id = $manifest['id'];

            require_once $dir . '/Provider.php';

            $ns = $loader->folderToNamespace($entry['folder']);
            $providerClass = $manifest['provider_class'] ?? "{$ns}\\Provider";

            if (!class_exists($providerClass)) {
                Logger::error("Provider class not found: {$providerClass} in {$entry['folder']}");
                continue;
            }

            $extractor = $this->loadExtractor($dir, $ns, $manifest);

            /** @var AbstractProvider $provider */
            $provider = new $providerClass($this->http, $this->detector, $manifest);

            if ($extractor !== null) {
                $provider->setDownloadExtractor($extractor);
            }

            $registry->register($provider);
            $pluginsOut[$id] = [
                'manifest' => $manifest,
                'folder' => $entry['folder'],
                'dir' => $dir,
                'class' => $providerClass,
                'service_type' => $serviceType,
            ];

            $platformsOut[$id] = $this->manifestToPlatform($manifest, $serviceType);
        }
    }

    private function loadExtractor(string $dir, string $ns, array $manifest): ?object
    {
        $extractorFile = $dir . '/Extractor.php';
        if (!is_file($extractorFile)) {
            return null;
        }

        require_once $extractorFile;
        $extractorClass = $manifest['extractor_class'] ?? "{$ns}\\Extractor";

        if (!class_exists($extractorClass)) {
            return null;
        }

        return new $extractorClass($this->http, $this->config);
    }

    private function manifestToPlatform(array $manifest, string $serviceType): array
    {
        $suffix = $serviceType === 'audio' ? ' Audio Downloader' : ' Video Downloader';

        return [
            'key' => $manifest['id'],
            'name' => $manifest['name'],
            'slug' => $manifest['slug'],
            'title' => $manifest['title'] ?? ($manifest['name'] . $suffix),
            'h1' => $manifest['h1'] ?? ($manifest['name'] . $suffix),
            'meta_description' => $manifest['meta_description'] ?? '',
            'description' => $manifest['description'] ?? '',
            'icon' => $manifest['icon'] ?? $manifest['id'],
            'keywords' => $manifest['keywords'] ?? '',
            'supported_domains' => $manifest['supported_domains'] ?? [],
            'how_to' => $manifest['how_to'] ?? [],
            'faq' => $manifest['faq'] ?? [],
            'url_examples' => $manifest['url_examples'] ?? [],
            'download_supported' => $manifest['download_supported'] ?? true,
            'folder' => $manifest['folder'],
            'service_type' => $serviceType,
        ];
    }

    public function getVideoRegistry(): ProviderRegistry
    {
        return $this->videoRegistry;
    }

    public function getAudioRegistry(): ProviderRegistry
    {
        return $this->audioRegistry;
    }

    /** Backward compatible – video registry. */
    public function getRegistry(): ProviderRegistry
    {
        return $this->videoRegistry;
    }

    /** @return array<string, array> */
    public function getVideoPlatforms(): array
    {
        return $this->videoPlatforms;
    }

    /** @return array<string, array> */
    public function getAudioPlatforms(): array
    {
        return $this->audioPlatforms;
    }

    /** @return array<string, array> Video platforms (legacy). */
    public function getPlatforms(): array
    {
        return $this->videoPlatforms;
    }

    /** @return array<string, array> */
    public function getVideoPlugins(): array
    {
        return $this->videoPlugins;
    }

    /** @return array<string, array> */
    public function getAudioPlugins(): array
    {
        return $this->audioPlugins;
    }

    /** @return array<string, array> Video plugins (legacy). */
    public function getPlugins(): array
    {
        return $this->videoPlugins;
    }

    public function getRegistryForService(string $serviceId): ProviderRegistry
    {
        return $serviceId === \App\ServiceConfig::SERVICE_AUDIO
            ? $this->audioRegistry
            : $this->videoRegistry;
    }

    public function getDetector(): PlatformDetector
    {
        return $this->detector;
    }

    public function getPlatformBySlug(string $slug, string $serviceType = 'video'): ?array
    {
        $platforms = $serviceType === 'audio' ? $this->audioPlatforms : $this->videoPlatforms;
        foreach ($platforms as $platform) {
            if ($platform['slug'] === $slug) {
                return $platform;
            }
        }

        return null;
    }

    public function getVideoLoader(): ProviderLoader
    {
        return $this->videoLoader;
    }

    public function getAudioLoader(): ProviderLoader
    {
        return $this->audioLoader;
    }

    /** @deprecated use getVideoLoader() */
    public function getLoader(): ProviderLoader
    {
        return $this->videoLoader;
    }
}
