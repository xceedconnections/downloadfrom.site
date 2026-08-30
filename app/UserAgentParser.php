<?php

declare(strict_types=1);

namespace App;

/** Lightweight user-agent parsing for visitor analytics. */
final class UserAgentParser
{
    /** @return array{browser: string, os_name: string, device_type: string} */
    public static function parse(string $userAgent): array
    {
        $ua = trim($userAgent);
        if ($ua === '') {
            return ['browser' => 'Unknown', 'os_name' => 'Unknown', 'device_type' => 'unknown'];
        }

        $device = 'desktop';
        if (preg_match('/Mobile|Android.*Mobile|iPhone|iPod|Windows Phone/i', $ua)) {
            $device = 'mobile';
        } elseif (preg_match('/iPad|Tablet|Android(?!.*Mobile)/i', $ua)) {
            $device = 'tablet';
        } elseif (preg_match('/Smart-TV|SmartTV|AppleTV|CrKey|Roku|HbbTV/i', $ua)) {
            $device = 'tv';
        } elseif (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview/i', $ua)) {
            $device = 'bot';
        }

        $os = 'Unknown';
        if (preg_match('/Windows NT 10/i', $ua)) {
            $os = 'Windows 10/11';
        } elseif (preg_match('/Windows NT/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS X ([0-9_\.]+)/i', $ua, $m)) {
            $os = 'macOS ' . str_replace('_', '.', $m[1]);
        } elseif (preg_match('/Android ([0-9\.]+)/i', $ua, $m)) {
            $os = 'Android ' . $m[1];
        } elseif (preg_match('/iPhone OS ([0-9_]+)/i', $ua, $m)) {
            $os = 'iOS ' . str_replace('_', '.', $m[1]);
        } elseif (preg_match('/iPad.*OS ([0-9_]+)/i', $ua, $m)) {
            $os = 'iPadOS ' . str_replace('_', '.', $m[1]);
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
        } elseif (preg_match('/CrOS/i', $ua)) {
            $os = 'Chrome OS';
        }

        $browser = 'Unknown';
        if (preg_match('/Edg\/([0-9\.]+)/i', $ua, $m)) {
            $browser = 'Edge ' . $m[1];
        } elseif (preg_match('/OPR\/([0-9\.]+)/i', $ua, $m)) {
            $browser = 'Opera ' . $m[1];
        } elseif (preg_match('/Chrome\/([0-9\.]+)/i', $ua, $m) && !preg_match('/Edg\/|OPR\//i', $ua)) {
            $browser = 'Chrome ' . $m[1];
        } elseif (preg_match('/Firefox\/([0-9\.]+)/i', $ua, $m)) {
            $browser = 'Firefox ' . $m[1];
        } elseif (preg_match('/Version\/([0-9\.]+).*Safari/i', $ua, $m) && !preg_match('/Chrome|Chromium/i', $ua)) {
            $browser = 'Safari ' . $m[1];
        } elseif (preg_match('/SamsungBrowser\/([0-9\.]+)/i', $ua, $m)) {
            $browser = 'Samsung Internet ' . $m[1];
        } elseif (preg_match('/bot|crawl|spider/i', $ua)) {
            $browser = 'Bot';
        }

        return [
            'browser' => mb_substr($browser, 0, 64),
            'os_name' => mb_substr($os, 0, 64),
            'device_type' => $device,
        ];
    }
}
