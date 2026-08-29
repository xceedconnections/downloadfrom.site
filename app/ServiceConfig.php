<?php

declare(strict_types=1);

namespace App;

/**
 * Download services (Video / Audio) and provider assignment.
 */
class ServiceConfig
{
    public const SERVICE_VIDEO = 'download-video';
    public const SERVICE_AUDIO = 'download-audio';
    public const SERVICE_ALL = 'all';

    public const PAGE_VIDEO = 'video-converter';
    public const PAGE_AUDIO = 'audio-converter';
    public const PAGE_FAQ = 'faq';

    /** @return array<string, array<string, mixed>> */
    public static function defaultServices(): array
    {
        return [
            self::SERVICE_VIDEO => [
                'enabled' => true,
                'name' => 'Video Converter',
                'nav_label' => 'Video Converter',
                'providers' => [],
            ],
            self::SERVICE_AUDIO => [
                'enabled' => true,
                'name' => 'Audio Converter',
                'nav_label' => 'Audio Converter',
                'providers' => [],
            ],
        ];
    }

    public static function servicePageSlug(string $serviceId): string
    {
        return match ($serviceId) {
            self::SERVICE_VIDEO => self::PAGE_VIDEO,
            self::SERVICE_AUDIO => self::PAGE_AUDIO,
            default => '',
        };
    }

    public static function serviceIdFromPageSlug(string $slug): ?string
    {
        return match ($slug) {
            self::PAGE_VIDEO => self::SERVICE_VIDEO,
            self::PAGE_AUDIO => self::SERVICE_AUDIO,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public static function getServices(Settings $settings): array
    {
        $saved = $settings->get('services', []);
        if (!is_array($saved)) {
            $saved = [];
        }

        $merged = array_replace_recursive(self::defaultServices(), $saved);
        foreach ($merged as $serviceId => &$service) {
            if (!empty($service['name'])) {
                $service['nav_label'] = (string) $service['name'];
            }
        }
        unset($service);

        return $merged;
    }

    public static function isServiceEnabled(Settings $settings, string $serviceId): bool
    {
        $services = self::getServices($settings);
        return !empty($services[$serviceId]['enabled']);
    }

    public static function serviceType(string $serviceId): string
    {
        return $serviceId === self::SERVICE_AUDIO ? 'audio' : 'video';
    }

    public static function settingsKeyForService(string $serviceId): string
    {
        return $serviceId === self::SERVICE_AUDIO ? 'audio_providers' : 'providers';
    }

    /**
     * Empty providers list = all enabled providers for that service type are included.
     */
    public static function isProviderAssigned(Settings $settings, string $serviceId, string $providerId): bool
    {
        $services = self::getServices($settings);
        $assigned = $services[$serviceId]['providers'] ?? [];
        if (!is_array($assigned) || $assigned === []) {
            return true;
        }

        return in_array($providerId, $assigned, true);
    }

    /**
     * @param array<string, array> $videoPlatforms
     * @param array<string, array> $audioPlatforms
     * @return array<int, array<string, mixed>>
     */
    public static function buildNavigation(Settings $settings, array $videoPlatforms, array $audioPlatforms): array
    {
        $services = self::getServices($settings);
        $nav = [];

        foreach ([self::SERVICE_VIDEO => $videoPlatforms, self::SERVICE_AUDIO => $audioPlatforms] as $serviceId => $platformMap) {
            if (!self::isServiceEnabled($settings, $serviceId)) {
                continue;
            }

            $platforms = [];
            foreach ($platformMap as $id => $platform) {
                if (!self::isProviderAssigned($settings, $serviceId, (string) $id)) {
                    continue;
                }
                $platforms[] = array_merge($platform, ['id' => $id, 'service' => $serviceId]);
            }

            if ($platforms === []) {
                continue;
            }

            $nav[] = [
                'id' => $serviceId,
                'name' => (string) ($services[$serviceId]['name'] ?? $serviceId),
                'nav_label' => (string) ($services[$serviceId]['name'] ?? $services[$serviceId]['nav_label'] ?? $serviceId),
                'page_slug' => self::servicePageSlug($serviceId),
                'platforms' => $platforms,
            ];
        }

        return $nav;
    }

    /** @param array<string, array> $videoPlatforms @param array<string, array> $audioPlatforms */
    public static function findPlatformBySlug(
        string $slug,
        array $videoPlatforms,
        array $audioPlatforms
    ): ?array {
        foreach ($videoPlatforms as $id => $platform) {
            if (($platform['slug'] ?? '') === $slug) {
                return array_merge($platform, [
                    'id' => $id,
                    'service' => self::SERVICE_VIDEO,
                    'service_type' => 'video',
                ]);
            }
        }

        foreach ($audioPlatforms as $id => $platform) {
            if (($platform['slug'] ?? '') === $slug) {
                return array_merge($platform, [
                    'id' => $id,
                    'service' => self::SERVICE_AUDIO,
                    'service_type' => 'audio',
                ]);
            }
        }

        return null;
    }

    public static function isValidServiceChoice(string $serviceId): bool
    {
        return in_array($serviceId, [self::SERVICE_ALL, self::SERVICE_VIDEO, self::SERVICE_AUDIO], true);
    }

    public static function serviceLabel(string $serviceId, Settings $settings): string
    {
        $services = self::getServices($settings);

        return match ($serviceId) {
            self::SERVICE_ALL => 'All (Video + Audio)',
            self::SERVICE_VIDEO => (string) ($services[self::SERVICE_VIDEO]['name'] ?? 'Video Downloader'),
            self::SERVICE_AUDIO => (string) ($services[self::SERVICE_AUDIO]['name'] ?? 'Audio Downloader'),
            default => 'Downloader',
        };
    }

    /** @return string[] */
    public static function availableServicesForPlatform(Settings $settings, string $platformId): array
    {
        $available = [];

        foreach ([self::SERVICE_VIDEO => 'video', self::SERVICE_AUDIO => 'audio'] as $serviceId => $serviceType) {
            if (!self::isServiceEnabled($settings, $serviceId)) {
                continue;
            }
            if (!self::isProviderAssigned($settings, $serviceId, $platformId)) {
                continue;
            }
            if (!PlatformConfig::isEnabled($settings, $platformId, $serviceType)) {
                continue;
            }
            $available[] = $serviceId;
        }

        return $available;
    }
}
