<?php

declare(strict_types=1);

namespace App;

/**
 * Shared yt-dlp execution helpers for provider extractors.
 */
class YtDlpHelper
{
    /** Prefer clients that return full DASH/progressive format lists. */
    private const PLAYER_CLIENT_ATTEMPTS = [
        null,
        'web',
        'mweb,web',
        'web_creator,web',
        'tv,web',
        'tv_embedded,web',
        'ios,web',
        'android,web',
        'android_vr,web',
    ];

    /** @param array<string, mixed> $config */
    public static function resolvePath(array $config): ?string
    {
        $root = dirname(__DIR__);
        $candidates = [];

        if (!empty($config['ytdlp']['enabled']) && !empty($config['ytdlp']['path'])) {
            $candidates[] = (string) $config['ytdlp']['path'];
        }

        $candidates[] = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'yt-dlp';
        $candidates[] = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'yt-dlp.exe';
        $candidates[] = '/www/wwwroot/downloadfrom.site/bin/yt-dlp';
        $candidates[] = 'yt-dlp';
        $candidates[] = 'yt-dlp.exe';

        foreach (array_unique($candidates) as $path) {
            if ($path === 'yt-dlp' || $path === 'yt-dlp.exe') {
                $test = self::exec(escapeshellarg($path) . ' --version 2>&1');
                if ($test !== null && preg_match('/^\d+\./', trim($test))) {
                    return $path;
                }
                continue;
            }

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $config */
    public static function resolveNodePath(array $config = []): ?string
    {
        $candidates = [];

        $configured = trim((string) ($config['ytdlp']['node_path'] ?? ''));
        if ($configured !== '') {
            $candidates[] = $configured;
        }

        $candidates[] = '/usr/bin/node';
        $candidates[] = '/usr/local/bin/node';
        if ($pf = getenv('ProgramFiles')) {
            $candidates[] = $pf . DIRECTORY_SEPARATOR . 'nodejs' . DIRECTORY_SEPARATOR . 'node.exe';
        }
        if ($pf86 = getenv('ProgramFiles(x86)')) {
            $candidates[] = $pf86 . DIRECTORY_SEPARATOR . 'nodejs' . DIRECTORY_SEPARATOR . 'node.exe';
        }
        $candidates[] = 'node.exe';
        $candidates[] = 'node';

        foreach (array_unique($candidates) as $path) {
            if ($path === 'node' || $path === 'node.exe') {
                $test = self::exec(escapeshellarg($path) . ' --version 2>&1');
                if ($test !== null && preg_match('/^v?\d+\./', trim($test))) {
                    return $path;
                }
                continue;
            }

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $config */
    public static function environmentStatus(array $config): array
    {
        $ytdlp = self::resolvePath($config);
        $node = self::resolveNodePath($config);
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

        $ytdlpVersion = null;
        if ($ytdlp !== null) {
            $ytdlpVersion = trim((string) self::exec(escapeshellarg($ytdlp) . ' --version 2>&1'));
        }

        $nodeVersion = null;
        if ($node !== null) {
            $nodeVersion = trim((string) self::exec(escapeshellarg($node) . ' --version 2>&1'));
        }

        return [
            'ytdlp' => $ytdlp,
            'ytdlp_version' => $ytdlpVersion,
            'node' => $node,
            'node_version' => $nodeVersion,
            'proc_open' => function_exists('proc_open') && !in_array('proc_open', $disabled, true),
            'shell_exec' => self::canShellExec(),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array{player_clients?: string|null, format?: string|null, remote_components?: bool|string|null} $options
     */
    public static function fetchJson(string $url, array $config = [], array $options = []): ?array
    {
        if (array_key_exists('player_clients', $options) && !array_key_exists('remote_components', $options)) {
            return self::fetchJsonAttempt($url, $config, $options);
        }

        $mergedFormats = [];
        $baseData = null;
        $nodePath = self::resolveNodePath($config);
        if ($nodePath === null) {
            Logger::error('Node.js not found — YouTube may return 360p only. Set ytdlp.node_path in config.local.php');
        }

        $remoteModes = ['github', 'npm', false];
        foreach ($remoteModes as $remoteMode) {
            foreach (self::PLAYER_CLIENT_ATTEMPTS as $playerClients) {
                $data = self::fetchJsonAttempt($url, $config, [
                    'player_clients' => $playerClients,
                    'format' => $options['format'] ?? null,
                    'remote_components' => $remoteMode,
                ]);
                if ($data === null) {
                    continue;
                }

                if ($baseData === null) {
                    $baseData = $data;
                }

                foreach ($data['formats'] ?? [] as $format) {
                    if (!is_array($format)) {
                        continue;
                    }

                    $formatId = (string) ($format['format_id'] ?? '');
                    if ($formatId === '') {
                        continue;
                    }

                    if (!isset($mergedFormats[$formatId]) || self::isRicherFormat($format, $mergedFormats[$formatId])) {
                        $mergedFormats[$formatId] = $format;
                    }
                }

                if (self::isStrongExtract(array_values($mergedFormats))) {
                    break 2;
                }
            }

            if ($mergedFormats !== [] && self::isStrongExtract(array_values($mergedFormats))) {
                break;
            }
        }

        if ($baseData === null || $mergedFormats === []) {
            return null;
        }

        $baseData['formats'] = array_values($mergedFormats);

        return $baseData;
    }

    /** @param array<int, array<string, mixed>> $formats */
    private static function isStrongExtract(array $formats): bool
    {
        if ($formats === []) {
            return false;
        }

        $videoLinks = YtDlpFormatLinks::buildVideoLinks($formats);
        $audioLinks = YtDlpFormatLinks::buildAudioMp3Links($formats);

        $maxHeight = 0;
        foreach ($videoLinks as $link) {
            $maxHeight = max($maxHeight, (int) preg_replace('/\D+/', '', (string) ($link['quality'] ?? '0')));
        }

        return count($videoLinks) >= 6 && count($audioLinks) >= 3 && $maxHeight >= 720;
    }

    /** @param array<string, mixed> $new @param array<string, mixed> $current */
    private static function isRicherFormat(array $new, array $current): bool
    {
        $newUrl = trim((string) ($new['url'] ?? ''));
        $curUrl = trim((string) ($current['url'] ?? ''));
        if ($newUrl !== '' && $curUrl === '') {
            return true;
        }
        if ($newUrl === '' && $curUrl !== '') {
            return false;
        }

        $newSize = (int) ($new['filesize'] ?? $new['filesize_approx'] ?? 0);
        $curSize = (int) ($current['filesize'] ?? $current['filesize_approx'] ?? 0);
        if ($newSize > 0 && $curSize > 0 && $newSize !== $curSize) {
            return $newSize > $curSize;
        }

        return ((int) ($new['tbr'] ?? 0)) > ((int) ($current['tbr'] ?? 0));
    }

    /**
     * @param array<string, mixed> $config
     * @param array{player_clients?: string|null, format?: string|null, remote_components?: bool|string|null} $options
     */
    private static function fetchJsonAttempt(string $url, array $config, array $options): ?array
    {
        $ytdlpPath = self::resolvePath($config);
        if ($ytdlpPath === null) {
            Logger::error('yt-dlp binary not found');
            return null;
        }

        $nodePath = self::resolveNodePath($config);
        $jsRuntimeArg = $nodePath !== null
            ? ' --js-runtimes ' . escapeshellarg('node:' . $nodePath)
            : '';

        $playerClients = $options['player_clients'] ?? null;
        $remoteComponents = $options['remote_components'] ?? 'github';

        $cmd = escapeshellarg($ytdlpPath)
            . ' -j --no-playlist --no-warnings --no-check-certificates'
            . ' --no-cache-dir'
            . ' --socket-timeout 30'
            . ' --extractor-retries 3';

        if ($remoteComponents !== false && $remoteComponents !== null && $remoteComponents !== '') {
            $remote = is_string($remoteComponents) ? $remoteComponents : 'github';
            $cmd .= ' --remote-components ejs:' . $remote;
        }

        if ($playerClients !== null && $playerClients !== '') {
            $cmd .= ' --extractor-args ' . escapeshellarg('youtube:player_client=' . $playerClients);
        }

        if (!empty($options['format'])) {
            $cmd .= ' -f ' . escapeshellarg((string) $options['format']);
        }

        $cmd .= $jsRuntimeArg
            . ' ' . escapeshellarg($url)
            . ' 2>&1';

        $output = self::exec($cmd, 120);
        if ($output === null || trim($output) === '') {
            Logger::error('yt-dlp empty output for ' . substr($url, 0, 80));
            return null;
        }

        $data = self::parseJsonOutput($output);
        if (!is_array($data) || empty($data['formats'])) {
            Logger::error('yt-dlp parse failed: ' . substr($output, 0, 400));
            return null;
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    public static function parseJsonOutput(string $output): ?array
    {
        $output = trim($output);
        $data = json_decode($output, true);
        if (is_array($data)) {
            return $data;
        }

        $start = strpos($output, '{');
        $end = strrpos($output, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $data = json_decode(substr($output, $start, $end - $start + 1), true);
        return is_array($data) ? $data : null;
    }

    public static function exec(string $command, int $timeoutSeconds = 90): ?string
    {
        if (function_exists('proc_open')) {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = @proc_open($command, $descriptors, $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]);
                stream_set_timeout($pipes[1], max(10, $timeoutSeconds));
                stream_set_timeout($pipes[2], max(10, $timeoutSeconds));
                $stdout = stream_get_contents($pipes[1]) ?: '';
                $stderr = stream_get_contents($pipes[2]) ?: '';
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                $combined = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));
                if ($combined !== '') {
                    return $combined;
                }
            }
        }

        if (self::canShellExec()) {
            $output = shell_exec($command);
            if ($output !== null && $output !== '') {
                return $output;
            }
        }

        return null;
    }

    public static function canShellExec(): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }

        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        return !in_array('shell_exec', $disabled, true);
    }
}
