<?php

declare(strict_types=1);

namespace App;

/**
 * Site-wide custom HTML/scripts from admin settings (AdSense, analytics, pop tags, etc.).
 * Vignette tags load at body start; popunder tags defer until after the first click so
 * desktop browsers show the vignette modal instead of an invisible click-capture layer.
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

    public static function renderBodyStart(?Settings $settings, string $baseUrl = '', bool $relayScripts = false): void
    {
        $html = self::bodyEndHtml($settings);
        if ($html === '') {
            return;
        }

        if ($baseUrl !== '' && $relayScripts) {
            $html = AdScriptRelay::rewriteMarkup($html, $baseUrl, true);
        }

        [$immediate, $deferred] = self::splitPopScriptTags($html);

        if ($immediate !== '') {
            echo "\n" . $immediate . "\n";
        }

        if ($deferred !== '') {
            echo "\n" . self::deferPopScripts($deferred) . "\n";
        }
    }

    /** @deprecated Pop tags load via renderBodyStart(); kept for backward compatibility. */
    public static function renderBodyEnd(?Settings $settings, string $baseUrl = '', bool $relayScripts = false): void
    {
    }

    /**
     * @return array{0: string, 1: string} immediate HTML, deferred popunder HTML
     */
    private static function splitPopScriptTags(string $html): array
    {
        if (!preg_match_all('/<script\b[^>]*>.*?<\/script>\s*|<script\b[^>]*\/>\s*/is', $html, $matches)) {
            return [$html, ''];
        }

        $immediate = [];
        $deferred = [];
        $other = [];

        foreach ($matches[0] as $tag) {
            if (self::isVignetteScript($tag)) {
                $immediate[] = $tag;
            } elseif (self::isPopunderScript($tag)) {
                $deferred[] = $tag;
            } else {
                $other[] = $tag;
            }
        }

        $remainder = trim(str_replace($matches[0], '', $html));
        $immediateHtml = implode("\n", array_merge($immediate, $other));
        if ($remainder !== '') {
            $immediateHtml = trim($immediateHtml . "\n" . $remainder);
        }

        return [trim($immediateHtml), trim(implode("\n", $deferred))];
    }

    private static function isVignetteScript(string $tag): bool
    {
        return (bool) preg_match('/vignette\.min\.js|n6wxm\.com/i', $tag);
    }

    private static function isPopunderScript(string $tag): bool
    {
        return (bool) preg_match(
            '/profitableratecpmnetwork|quge5\.com|tag\.min\.js|popunder|onclickpop/i',
            $tag
        );
    }

    private static function deferPopScripts(string $html): string
    {
        $payload = json_encode($html, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($payload === false) {
            return $html;
        }

        return '<script data-df-defer-popunder>(function(){'
            . 'var h=' . $payload . ';'
            . 'function inject(){'
            . 'var t=document.createElement("template");'
            . 't.innerHTML=h;'
            . 'Array.prototype.forEach.call(t.content.querySelectorAll("script"),function(old){'
            . 'var s=document.createElement("script");'
            . 'Array.prototype.forEach.call(old.attributes,function(a){s.setAttribute(a.name,a.value);});'
            . 's.text=old.textContent;'
            . 'document.body.appendChild(s);'
            . '});'
            . '}'
            . 'function schedule(){setTimeout(inject,1200);}'
            . 'document.addEventListener("click",function once(){document.removeEventListener("click",once,true);schedule();},true);'
            . 'window.addEventListener("load",function(){setTimeout(inject,12000);});'
            . '})();</script>';
    }
}
