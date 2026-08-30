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

    public static function renderHead(?Settings $settings, string $baseUrl = '', bool $relayScripts = true): void
    {
        $html = self::headHtml($settings);
        if ($html === '') {
            return;
        }

        if ($baseUrl !== '') {
            $html = AdScriptRelay::rewriteMarkup($html, $baseUrl, $relayScripts);
        }

        echo "\n" . self::wrapMarkup($html, $baseUrl, $relayScripts) . "\n";
    }

    public static function renderBodyEnd(?Settings $settings, string $baseUrl = '', bool $relayScripts = true): void
    {
        $html = self::bodyEndHtml($settings);
        if ($html === '') {
            return;
        }

        if ($baseUrl !== '') {
            $html = AdScriptRelay::rewriteMarkup($html, $baseUrl, $relayScripts);
        }

        echo "\n<div id=\"site-custom-codes\" data-custom-codes=\"1\">"
            . self::wrapMarkup($html, $baseUrl, $relayScripts)
            . "</div>\n";
        echo "\n<script>(function(){function boot(){var el=document.getElementById('site-custom-codes');if(!el){return;}if(typeof window.__dfzActivateScripts==='function'){window.__dfzActivateScripts(el);}}if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',boot);}else{boot();}})();</script>\n";
    }

    private static function wrapMarkup(string $html, string $baseUrl, bool $relayScripts): string
    {
        if ($html === '') {
            return '';
        }

        if (!self::needsScriptIsolation($html)) {
            return $html;
        }

        $dims = self::parseInlineAdDimensions($html);
        $width = $dims['width'] > 0 ? $dims['width'] : 1;
        $height = $dims['height'] > 0 ? $dims['height'] : 1;
        $document = '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>html,body{margin:0;padding:0;overflow:hidden;background:transparent;}</style>'
            . '</head><body>' . $html . '</body></html>';
        $srcdoc = htmlspecialchars($document, ENT_QUOTES | ENT_HTML5);

        return '<iframe class="dfz-script-frame site-custom-code-frame" title="Site script" loading="lazy" '
            . 'sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox allow-forms allow-same-origin" '
            . 'srcdoc="' . $srcdoc . '" '
            . 'width="' . $width . '" height="' . $height . '" '
            . 'style="position:absolute;width:0;height:0;border:0;clip:rect(0 0 0 0);overflow:hidden;" '
            . 'aria-hidden="true" tabindex="-1"></iframe>';
    }

    private static function needsScriptIsolation(string $code): bool
    {
        if (!preg_match('/<script\b/i', $code)) {
            return false;
        }

        return preg_match('/atOptions\s*=/i', $code) === 1
            || preg_match('/invoke\.js/i', $code) === 1
            || preg_match('/popunder/i', $code) === 1
            || preg_match('/onclickpop/i', $code) === 1
            || preg_match_all('/<script\b/i', $code) > 1;
    }

    /** @return array{width: int, height: int} */
    private static function parseInlineAdDimensions(string $code): array
    {
        $width = 0;
        $height = 0;
        if (preg_match("/['\"]width['\"]\s*:\s*(\d+)/", $code, $m)) {
            $width = (int) $m[1];
        }
        if (preg_match("/['\"]height['\"]\s*:\s*(\d+)/", $code, $m)) {
            $height = (int) $m[1];
        }

        return ['width' => $width, 'height' => $height];
    }
}
