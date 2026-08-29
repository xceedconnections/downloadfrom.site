<?php

declare(strict_types=1);

namespace App;

/**
 * Shared yt-dlp execution helpers for provider extractors.
 */
class YtDlpHelper
{
    /** @param array<string, mixed> $config */
    public static function resolvePath(array $config): ?string
    {
        if (!empty($config['ytdlp']['enabled']) && !empty($config['ytdlp']['path'])) {
            $configured = (string) $config['ytdlp']['path'];
            if (is_file($configured)) {
                return $configured;
            }
        }

        $root = dirname(__DIR__);
        $candidates = [
            $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'yt-dlp.exe',
            $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'yt-dlp',
            'yt-dlp.exe',
            'yt-dlp',
        ];

        foreach ($candidates as $path) {
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
        $configured = trim((string) ($config['ytdlp']['node_path'] ?? ''));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $candidates = [];
        if ($pf = getenv('ProgramFiles')) {
            $candidates[] = $pf . DIRECTORY_SEPARATOR . 'nodejs' . DIRECTORY_SEPARATOR . 'node.exe';
        }
        if ($pf86 = getenv('ProgramFiles(x86)')) {
            $candidates[] = $pf86 . DIRECTORY_SEPARATOR . 'nodejs' . DIRECTORY_SEPARATOR . 'node.exe';
        }
        $candidates[] = 'node.exe';
        $candidates[] = 'node';

        foreach ($candidates as $path) {
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
        $ytdlpPath = self::resolvePath($config);
        if ($ytdlpPath === null) {
            return null;
        }

        $nodePath = self::resolveNodePath($config);
        $jsRuntimeArg = $nodePath !== null
            ? ' --js-runtimes ' . escapeshellarg('node:' . $nodePath)
            : ' --js-runtimes node';

        $playerClients = array_key_exists('player_clients', $options)
            ? $options['player_clients']
            : null;

        $cmd = escapeshellarg($ytdlpPath)
            . ' -j --no-playlist --no-warnings --no-check-certificates';

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
            Logger::error('yt-dlp returned empty output for: ' . substr($url, 0, 80));
            return null;
        }

        $data = self::parseJsonOutput($output);
        if (!is_array($data) || empty($data['formats'])) {
            Logger::error('yt-dlp JSON parse failed: ' . substr($output, 0, 300));
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
        if (self::canShellExec()) {
            $output = shell_exec($command);
            if ($output !== null && $output !== '') {
                return $output;
            }
        }

        if (!function_exists('proc_open')) {
            return null;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $combined = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));
        return $combined !== '' ? $combined : null;
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
