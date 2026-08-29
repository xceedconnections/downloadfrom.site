<?php

declare(strict_types=1);

/**
 * Generates per-provider {folder}Dlp.php (self-contained, no shared class).
 * Run: php tools/build-provider-extractors.php
 */

$root = dirname(__DIR__);
$providersPath = $root . '/app/provider';
if (!is_dir($providersPath)) {
    $providersPath = $root . '/app/video-provider';
}

$ytdlpBody = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Provider\{NS};

use App\Logger;

/**
 * yt-dlp extraction for {PLATFORM} — provider-local ({CLASS}).
 */
class {CLASS}
{
    private ?string $ytdlpPath;

    public function __construct(array $config = [])
    {
        $ytdlpPath = null;
        if (!empty($config['ytdlp']['enabled']) && !empty($config['ytdlp']['path']) && is_file($config['ytdlp']['path'])) {
            $ytdlpPath = $config['ytdlp']['path'];
        }
        $this->ytdlpPath = $ytdlpPath ?? $this->detectYtDlp();
    }

    public function isAvailable(): bool
    {
        return $this->ytdlpPath !== null;
    }

    public function extract(string $url): array
    {
        if ($this->ytdlpPath === null) {
            return [];
        }

        $cmd = escapeshellarg($this->ytdlpPath)
            . ' -j --no-playlist --no-warnings --no-check-certificates'
            . ' --js-runtimes node'
            . ' ' . escapeshellarg($url)
            . ' 2>&1';

        $output = shell_exec($cmd);
        if ($output === null || $output === '') {
            Logger::error('yt-dlp returned empty output for: ' . substr($url, 0, 80));
            return [];
        }

        $data = json_decode($output, true);
        if (!is_array($data) || empty($data['formats'])) {
            Logger::error('yt-dlp JSON parse failed: ' . substr($output, 0, 200));
            return [];
        }

        return $this->buildLinksFromFormats($data['formats']);
    }

    /** @param array<int, array<string, mixed>> $formats */
    public function buildLinksFromFormats(array $formats): array
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

        foreach ($heights as $height) {
            if ($height <= 0) {
                continue;
            }

            $format = $bestCombined[$height] ?? $bestVideo[$height] ?? null;
            if ($format === null) {
                continue;
            }

            $combined = isset($bestCombined[$height]);
            $ext = $format['ext'] ?? 'mp4';
            $filesize = (int) ($format['filesize'] ?? $format['filesize_approx'] ?? 0);
            $sizeLabel = $filesize > 0 ? ' (' . $this->formatBytes($filesize) . ')' : '';
            $label = "{$height}p " . strtoupper((string) $ext) . $sizeLabel;
            if (!$combined) {
                $label .= ' (Video)';
            }

            $links[] = $this->formatToLink($format, $label, "{$height}p", (string) $ext, $combined);
        }

        if ($bestAudio !== null) {
            $abr = (int) ($bestAudio['abr'] ?? 128);
            $ext = $bestAudio['ext'] ?? 'm4a';
            $filesize = (int) ($bestAudio['filesize'] ?? $bestAudio['filesize_approx'] ?? 0);
            $sizeLabel = $filesize > 0 ? ' (' . $this->formatBytes($filesize) . ')' : '';
            $links[] = $this->formatToLink(
                $bestAudio,
                "Audio {$abr}kbps " . strtoupper((string) $ext) . $sizeLabel,
                'audio',
                (string) $ext,
                false
            );
        }

        return $links;
    }

    /** @param array<string, mixed> $format */
    private function isDirectFormat(array $format): bool
    {
        $protocol = strtolower((string) ($format['protocol'] ?? ''));
        $url = (string) ($format['url'] ?? '');
        $ext = strtolower((string) ($format['ext'] ?? ''));

        if ($ext === 'mhtml' || str_starts_with((string) ($format['format_id'] ?? ''), 'sb')) {
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

    /** @param array<string, mixed> $format */
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

    /** @param array<string, mixed> $new @param array<string, mixed> $current */
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

    private function detectYtDlp(): ?string
    {
        $root = dirname(__DIR__, 3);
        $candidates = [
            $root . '/bin/yt-dlp.exe',
            $root . '/bin/yt-dlp',
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
}

PHP;

function folderToClassPrefix(string $folder): string
{
    $parts = preg_split('/[-_]/', $folder) ?: [$folder];
    $parts = array_map(static fn(string $p): string => ucfirst(strtolower($p)), $parts);

    return implode('', $parts);
}

$dirs = glob($providersPath . '/*', GLOB_ONLYDIR) ?: [];
$count = 0;

foreach ($dirs as $dir) {
    $folder = basename($dir);
    if (str_starts_with($folder, '.') || str_starts_with($folder, '_')) {
        continue;
    }
    if (!is_file($dir . '/Provider.php')) {
        continue;
    }

    $ns = folderToClassPrefix($folder);
    $className = $ns . 'Dlp';
    $fileName = $folder . 'Dlp.php';
    $content = str_replace(
        ['{NS}', '{PLATFORM}', '{CLASS}'],
        [$ns, $folder, $className],
        $ytdlpBody
    );
    file_put_contents($dir . '/' . $fileName, $content);
    @unlink($dir . '/YtDlp.php');
    echo "Created {$folder}/{$fileName}\n";
    $count++;
}

echo "Done: {$count} provider Dlp files.\n";
