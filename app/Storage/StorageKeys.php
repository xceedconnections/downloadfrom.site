<?php

declare(strict_types=1);

namespace App\Storage;

/**
 * Canonical store keys shared by JSON and MySQL drivers.
 */
final class StorageKeys
{
    public const SETTINGS = 'settings';
    public const FAQ = 'faq';
    public const ADS = 'ads';
    public const ADMIN = 'admin';
    public const ANALYTICS = 'analytics';
    public const RATE_LIMITS = 'rate_limits';

    public static function result(string $token): string
    {
        return 'results/' . $token;
    }

    /** Normalize legacy file names ("settings.json") to logical keys ("settings"). */
    public static function normalize(string $store): string
    {
        $store = ltrim(str_replace('\\', '/', $store), '/');
        $store = str_replace(['..', "\0"], '', $store);

        if (str_ends_with($store, '.json')) {
            $store = substr($store, 0, -5);
        }

        return $store;
    }

    /** Map a logical key to the on-disk JSON filename (JSON driver only). */
    public static function toJsonFilename(string $store): string
    {
        return self::normalize($store) . '.json';
    }

    /** @return list<string> Primary stores migrated to MySQL */
    public static function primaryStores(): array
    {
        return [
            self::SETTINGS,
            self::FAQ,
            self::ADS,
            self::ADMIN,
            self::ANALYTICS,
            self::RATE_LIMITS,
        ];
    }
}
