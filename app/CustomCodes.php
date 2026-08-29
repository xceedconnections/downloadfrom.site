<?php

declare(strict_types=1);

namespace App;

/**
 * Site-wide custom HTML/scripts from admin settings (AdSense, analytics, etc.).
 */
final class CustomCodes
{
    public static function headHtml(?Settings $settings): string
    {
        if ($settings === null) {
            return '';
        }

        return trim((string) $settings->get('custom_codes.head', ''));
    }

    public static function bodyEndHtml(?Settings $settings): string
    {
        if ($settings === null) {
            return '';
        }

        return trim((string) $settings->get('custom_codes.body_end', ''));
    }

    public static function renderHead(?Settings $settings): void
    {
        $html = self::headHtml($settings);
        if ($html === '') {
            return;
        }

        echo "\n" . $html . "\n";
    }

    public static function renderBodyEnd(?Settings $settings): void
    {
        $html = self::bodyEndHtml($settings);
        if ($html === '') {
            return;
        }

        echo "\n" . $html . "\n";
    }
}
