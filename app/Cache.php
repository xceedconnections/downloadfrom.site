<?php

declare(strict_types=1);

namespace App;

class Cache
{
    private string $cachePath;
    private int $ttl;
    private bool $enabled;

    public function __construct(array $config)
    {
        $this->cachePath = rtrim($config['cache']['path'], '/\\');
        $this->ttl = (int) $config['cache']['ttl'];
        $this->enabled = (bool) $config['cache']['enabled'];

        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0750, true);
        }
    }

    public function get(string $normalizedUrl): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        $file = $this->filePath($normalizedUrl);
        if (!is_file($file)) {
            return null;
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return null;
        }

        flock($handle, LOCK_SH);
        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        if ($content === false || $content === '') {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['timestamp'])) {
            return null;
        }

        if (time() - (int) $data['timestamp'] > $this->ttl) {
            @unlink($file);
            return null;
        }

        unset($data['timestamp']);

        return $data;
    }

    public function set(string $normalizedUrl, array $data): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if (!empty($data['private'])) {
            return false;
        }

        $data['timestamp'] = time();
        $file = $this->filePath($normalizedUrl);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }

        if (!rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }

        @chmod($file, 0640);
        return true;
    }

    public function delete(string $normalizedUrl): bool
    {
        $file = $this->filePath($normalizedUrl);
        return is_file($file) ? unlink($file) : true;
    }

    public function pathForKey(string $key): string
    {
        return $this->cachePath . '/' . $key . '.json';
    }

    public function clear(): int
    {
        $count = 0;
        $files = glob($this->cachePath . '/*.json') ?: [];
        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    public function count(): int
    {
        return count(glob($this->cachePath . '/*.json') ?: []);
    }

    /** @return array{enabled: bool, path: string, ttl: int, total_files: int, active_files: int} */
    public function stats(): array
    {
        $total = 0;
        $active = 0;
        $now = time();

        foreach (glob($this->cachePath . '/*.json') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }

            $total++;
            $content = @file_get_contents($file);
            if ($content === false || $content === '') {
                continue;
            }

            $data = json_decode($content, true);
            if (!is_array($data) || !isset($data['timestamp'])) {
                continue;
            }

            if ($now - (int) $data['timestamp'] <= $this->ttl) {
                $active++;
            }
        }

        return [
            'enabled' => $this->enabled,
            'path' => $this->cachePath,
            'ttl' => $this->ttl,
            'total_files' => $total,
            'active_files' => $active,
        ];
    }

    private function filePath(string $normalizedUrl): string
    {
        return $this->cachePath . '/' . hash('sha256', $normalizedUrl) . '.json';
    }
}
