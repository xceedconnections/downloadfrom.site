<?php

declare(strict_types=1);

namespace App;

/**
 * Site-wide custom HTML/scripts from admin settings (AdSense, analytics, pop tags, etc.).
 * Output is passed through as-is so vignette/popunder tags work on the main document.
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

    public static function renderHead(?Settings $settings, string $baseUrl = '', bool $relayScripts = false): void
    {
        $html = self::headHtml($settings);
        if ($html === '') {
            return;
        }

        if ($baseUrl !== '' && $relayScripts) {
            $html = AdScriptRelay::rewriteMarkup($html, $baseUrl, true);
        }

        echo "\n" . $html . "\n";
    }

    public static function renderBodyEnd(?Settings $settings, string $baseUrl = '', bool $relayScripts = false): void
    {
        $html = self::bodyEndHtml($settings);
        if ($html === '') {
            return;
        }

        if ($baseUrl !== '' && $relayScripts) {
            $html = AdScriptRelay::rewriteMarkup($html, $baseUrl, true);
        }

        echo "\n" . $html . "\n";
    }
}
