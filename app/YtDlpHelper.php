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
        'tv,web',
        'ios,web',
        'android,web',
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

    /**
     * @param array<string, mixed> $config
     * @param array{player_clients?: string|null, format?: string|null} $options
     */
    public static function fetchJson(string $url, array $config = [], array $options = []): ?array
    {
        if (array_key_exists('player_clients', $options)) {
            return self::fetchJsonAttempt($url, $config, $options);
        }

        $best = null;
        $bestScore = 0;

        foreach (self::PLAYER_CLIENT_ATTEMPTS as $playerClients) {
            $data = self::fetchJsonAttempt($url, $config, [
                'player_clients' => $playerClients,
                'format' => $options['format'] ?? null,
            ]);
            $score = self::scoreExtract($data);
            if ($score > $bestScore) {
                $best = $data;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /** @param array<string, mixed>|null $data */
    private static function scoreExtract(?array $data): int
    {
        if ($data === null) {
            return 0;
        }

        $formats = $data['formats'] ?? [];
        if (!is_array($formats) || $formats === []) {
            return 0;
        }

        $videoLinks = YtDlpFormatLinks::buildVideoLinks($formats);
        $audioLinks = YtDlpFormatLinks::buildAudioMp3Links($formats);

        $maxHeight = 0;
        foreach ($videoLinks as $link) {
            $height = (int) preg_replace('/\D+/', '', (string) ($link['quality'] ?? '0'));
            $maxHeight = max($maxHeight, $height);
        }

        return (count($videoLinks) * 100000)
            + (count($audioLinks) * 10000)
            + ($maxHeight * 10)
            + count($formats);
    }

    /**
     * @param array<string, mixed> $config
     * @param array{player_clients?: string|null, format?: string|null} $options
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

        $cmd = escapeshellarg($ytdlpPath)
            . ' -j --no-playlist --no-warnings --no-check-certificates'
            . ' --no-cache-dir'
            . ' --remote-components ejs:github';

        if ($playerClients !== null && $playerClients !== '') {
            $cmd .= ' --extractor-args ' . escapeshellarg('youtube:player_client=' . $playerClients);
        }

        if (!empty($options['format'])) {
            $cmd .= ' -f ' . escapeshellarg((string) $options['format']);
        }

        $cmd .= $jsRuntimeArg
            . ' ' . escapeshellarg($url)
            . ' 2>&1';

        $output = self::exec($cmd);
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

    public static function exec(string $command): ?string
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

    private static function canShellExec(): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }

        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        return !in_array('shell_exec', $disabled, true);
    }
}
