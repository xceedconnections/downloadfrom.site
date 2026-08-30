<?php

declare(strict_types=1);

namespace App;

/**
 * Site-wide custom HTML/scripts from admin settings (AdSense, analytics, pop tags, etc.).
 * Pop/vignette tags are output in <head> on the main document (required for desktop browsers).
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

    /** Head + body script fields combined for pop tags that must run on the main document. */
    public static function allHtml(?Settings $settings): string
    {
        $parts = array_filter([
            self::headHtml($settings),
            self::bodyEndHtml($settings),
        ], static fn(string $part): bool => $part !== '');

        return implode("\n", $parts);
    }

    public static function renderHead(?Settings $settings, string $baseUrl = '', bool $relayScripts = false): void
    {
        $html = self::allHtml($settings);
        if ($html === '') {
            return;
        }

        if ($baseUrl !== '' && $relayScripts) {
            $html = AdScriptRelay::rewriteMarkup($html, $baseUrl, true);
        }

        echo "\n" . $html . "\n";
    }

    /** @deprecated Body scripts are merged into renderHead() for desktop pop/vignette support. */
    public static function renderBodyEnd(?Settings $settings, string $baseUrl = '', bool $relayScripts = false): void
    {
        // Pop tags load once via renderHead() — avoid duplicate execution.
    }
}
