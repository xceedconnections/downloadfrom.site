<?php

declare(strict_types=1);

namespace App;

/**
 * YouTube download link extraction via yt-dlp (primary) with page-scrape fallback.
 */
class YouTubeExtractor
{
    private HttpClient $http;
    private ?string $ytdlpPath;

    public function __construct(HttpClient $http, ?string $ytdlpPath = null)
    {
        $this->http = $http;
        $this->ytdlpPath = $ytdlpPath ?? $this->detectYtDlp();
    }

    public function extract(string $url): array
    {
        if ($this->ytdlpPath !== null) {
            $links = $this->extractViaYtDlp($url);
            if ($links !== []) {
                return $links;
            }
        }

        return $this->extractViaPageScrape($url);
    }

    private function extractViaYtDlp(string $url): array
    {
        $cmd = escapeshellarg($this->ytdlpPath)
            . ' -j --no-playlist --no-warnings --no-check-certificates'
            . ' --js-runtimes node'
            . ' ' . escapeshellarg($url)
            . ' 2>&1';

        $output = shell_exec($cmd);
        if ($output === null || $output === '') {
            Logger::error('yt-dlp returned empty output');
            return [];
        }

        $data = json_decode($output, true);
        if (!is_array($data) || empty($data['formats'])) {
            Logger::error('yt-dlp JSON parse failed: ' . substr($output, 0, 200));
            return [];
        }

        return $this->buildLinksFromYtDlp($data['formats']);
    }

    private function buildLinksFromYtDlp(array $formats): array
    {
        $links = [];
        $bestCombined = [];
        $bestVideo = [];
        $bestAudio = null;

        foreach ($formats as $format) {
            if (empty($format['url']) || !$this->isDirectFormat($format)) {
                continue;
            }

            $vcodec = $format['vcodec'] ?? 'none';
            $acodec = $format['acodec'] ?? 'none';
            $height = (int) ($format['height'] ?? 0);
            $ext = $format['ext'] ?? 'mp4';

            if ($vcodec !== 'none' && $acodec !== 'none') {
                if (!isset($bestCombined[$height]) || $this->isBetterFormat($format, $bestCombined[$height])) {
                    $bestCombined[$height] = $format;
                }
            } elseif ($vcodec !== 'none' && $acodec === 'none') {
                if (!isset($bestVideo[$height]) || $this->isBetterFormat($format, $bestVideo[$height])) {
                    $bestVideo[$height] = $format;
                }
            } elseif ($acodec !== 'none' && $vcodec === 'none') {
                $abr = (float) ($format['abr'] ?? 0);
                if ($bestAudio === null || $abr > (float) ($bestAudio['abr'] ?? 0)) {
                    $bestAudio = $format;
                }
            }
        }

        $heights = array_unique(array_merge(array_keys($bestCombined), array_keys($bestVideo)));
        rsort($heights);

        $seenHeights = [];
        foreach ($heights as $height) {
            if ($height <= 0 || isset($seenHeights[$height])) {
                continue;
            }
            $seenHeights[$height] = true;

            $format = $bestCombined[$height] ?? $bestVideo[$height] ?? null;
            if ($format === null) {
                continue;
            }

            $combined = isset($bestCombined[$height]);
            $ext = $format['ext'] ?? 'mp4';
            $filesize = (int) ($format['filesize'] ?? $format['filesize_approx'] ?? 0);
            $sizeLabel = $filesize > 0 ? ' (' . $this->formatBytes($filesize) . ')' : '';

            $label = "{$height}p " . strtoupper($ext) . $sizeLabel;
            if (!$combined) {
                $label .= ' (Video)';
            }

            $links[] = $this->formatToLink($format, $label, "{$height}p", $ext, $combined);
        }

        if ($bestAudio !== null) {
            $abr = (int) ($bestAudio['abr'] ?? 128);
            $ext = $bestAudio['ext'] ?? 'm4a';
            $filesize = (int) ($bestAudio['filesize'] ?? $bestAudio['filesize_approx'] ?? 0);
            $sizeLabel = $filesize > 0 ? ' (' . $this->formatBytes($filesize) . ')' : '';
            $links[] = $this->formatToLink(
                $bestAudio,
                "Audio {$abr}kbps " . strtoupper($ext) . $sizeLabel,
                'audio',
                $ext,
                false
            );
        }

        return $links;
    }

    /** Skip HLS/m3u8 manifests – only allow direct progressive/DASH file URLs */
    private function isDirectFormat(array $format): bool
    {
        $protocol = strtolower($format['protocol'] ?? '');
        $url = $format['url'] ?? '';
        $ext = strtolower($format['ext'] ?? '');

        if ($ext === 'mhtml' || str_starts_with($format['format_id'] ?? '', 'sb')) {
            return false;
        }

        if (str_contains($protocol, 'm3u8') || str_contains($protocol, 'dash')) {
            return false;
        }

        if (str_contains($url, '.m3u8') || str_contains($url, 'manifest.googlevideo.com')) {
            return false;
        }

        return $protocol === 'https' || $protocol === 'http';
    }

    private function formatToLink(array $format, string $label, string $quality, string $ext, bool $combined): array
    {
        $filesize = (int) ($format['filesize'] ?? $format['filesize_approx'] ?? 0);

        return [
            'type' => 'download',
            'label' => $label,
            'url' => $format['url'],
            'quality' => $quality,
            'download' => true,
            'ext' => $ext,
            'format_id' => $format['format_id'] ?? null,
            'combined' => $combined,
            'filesize' => $filesize > 0 ? $filesize : null,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 0) . ' KB';
        }
        return $bytes . ' B';
    }

    private function isBetterFormat(array $new, array $current): bool
    {
        $newExt = $new['ext'] ?? '';
        $curExt = $current['ext'] ?? '';
        if ($newExt === 'mp4' && $curExt !== 'mp4') {
            return true;
        }
        if ($newExt !== 'mp4' && $curExt === 'mp4') {
            return false;
        }
        $newSize = (int) ($new['filesize'] ?? $new['filesize_approx'] ?? 0);
        $curSize = (int) ($current['filesize'] ?? $current['filesize_approx'] ?? 0);
        if ($newSize > 0 && $curSize > 0 && $newSize !== $curSize) {
            return $newSize > $curSize;
        }
        return ((int) ($new['tbr'] ?? 0)) > ((int) ($current['tbr'] ?? 0));
    }

    /** Fallback: page scrape for 360p only when yt-dlp unavailable */
    private function extractViaPageScrape(string $url): array
    {
        $videoId = $this->videoId($url);
        if ($videoId === null) {
            return [];
        }

        $response = $this->http->get(
            "https://www.youtube.com/watch?v={$videoId}",
            [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Cookie: CONSENT=YES+1',
            ]
        );

        if (!$response['success'] || empty($response['body'])) {
            return [];
        }

        $data = $this->parsePlayerJson($response['body'], 'ytInitialPlayerResponse');
        if ($data === null) {
            return [];
        }

        $formats = $data['streamingData']['formats'] ?? [];
        foreach ($formats as $format) {
            if ((int) ($format['itag'] ?? 0) === 18 && !empty($format['signatureCipher'])) {
                parse_str($format['signatureCipher'], $parts);
                if (!empty($parts['url']) && !empty($parts['s'])) {
                    $sig = urldecode($parts['s']);
                    $baseUrl = urldecode($parts['url']);
                    $sp = $parts['sp'] ?? 'sig';
                    // Try raw sig (works on some videos)
                    $mediaUrl = $baseUrl . '&' . $sp . '=' . urlencode($sig);
                    return [[
                        'type' => 'download',
                        'label' => '360p MP4',
                        'url' => $mediaUrl,
                        'quality' => '360p',
                        'download' => true,
                        'ext' => 'mp4',
                    ]];
                }
            }
        }

        return [];
    }

    private function parsePlayerJson(string $html, string $variable): ?array
    {
        $marker = "{$variable} = ";
        $pos = strpos($html, $marker);
        if ($pos === false) {
            return null;
        }

        $start = $pos + strlen($marker);
        $json = $this->extractJsonObject($html, $start);
        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    private function extractJsonObject(string $html, int $start): ?string
    {
        $len = strlen($html);
        while ($start < $len && ctype_space($html[$start])) {
            $start++;
        }

        if ($start >= $len || $html[$start] !== '{') {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;

        for ($i = $start; $i < $len; $i++) {
            $char = $html[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($char === '\\' && $inString) {
                $escape = true;
                continue;
            }
            if ($char === '"') {
                $inString = !$inString;
                continue;
            }
            if ($inString) {
                continue;
            }
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($html, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    private function detectYtDlp(): ?string
    {
        $candidates = [
            dirname(__DIR__) . '/bin/yt-dlp.exe',
            dirname(__DIR__) . '/bin/yt-dlp',
            'yt-dlp',
            'yt-dlp.exe',
        ];

        foreach ($candidates as $path) {
            if ($path === 'yt-dlp' || $path === 'yt-dlp.exe') {
                $test = shell_exec(escapeshellarg($path) . ' --version 2>&1');
                if ($test !== null && preg_match('/^\d+\./', trim($test))) {
                    return $path;
                }
            } elseif (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function videoId(string $url): ?string
    {
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#youtu\.be/([a-zA-Z0-9_-]{11})#', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#/shorts/([a-zA-Z0-9_-]{11})#', $url, $m)) {
            return $m[1];
        }
        return null;
    }
}
