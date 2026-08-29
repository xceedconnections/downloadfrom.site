<?php



declare(strict_types=1);



namespace App;



use App\Contracts\StorageInterface;

use App\Storage\StorageKeys;



/**

 * JSON file storage driver (default).

 *

 * Each logical store key maps to storage/data/{key}.json

 * (e.g. "settings" -> settings.json, "results/abc" -> results/abc.json).

 */

class JsonDatabase implements StorageInterface

{

    private string $basePath;



    public function __construct(array $config)

    {

        $this->basePath = rtrim($config['storage']['path'], '/\\');

        $this->ensureDirectory($this->basePath);

    }



    public function read(string $store, mixed $default = []): mixed

    {

        $path = $this->path($store);

        if (!is_file($path)) {

            return $default;

        }



        $handle = fopen($path, 'rb');

        if ($handle === false) {

            return $default;

        }



        flock($handle, LOCK_SH);

        $content = stream_get_contents($handle);

        flock($handle, LOCK_UN);

        fclose($handle);



        if ($content === false || $content === '') {

            return $default;

        }



        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {

            Logger::error('JSON decode failed for ' . $store . ': ' . json_last_error_msg());

            return $default;

        }



        return $data;

    }



    public function write(string $store, mixed $data): bool

    {

        $path = $this->path($store);

        $dir = dirname($path);

        $this->ensureDirectory($dir);



        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {

            return false;

        }



        $tmp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';

        $written = file_put_contents($tmp, $json, LOCK_EX);

        if ($written === false) {

            @unlink($tmp);

            return false;

        }



        if (!rename($tmp, $path)) {

            @unlink($tmp);

            return false;

        }



        @chmod($path, 0640);

        return true;

    }



    public function update(string $store, callable $callback, mixed $default = []): bool

    {

        $path = $this->path($store);

        $this->ensureDirectory(dirname($path));



        $handle = fopen($path, file_exists($path) ? 'c+' : 'w+');

        if ($handle === false) {

            return false;

        }



        flock($handle, LOCK_EX);

        rewind($handle);

        $content = stream_get_contents($handle);

        $current = ($content !== false && $content !== '')

            ? json_decode($content, true)

            : $default;



        if ($content !== false && $content !== '' && json_last_error() !== JSON_ERROR_NONE) {

            flock($handle, LOCK_UN);

            fclose($handle);

            return false;

        }



        $updated = $callback($current ?? $default);

        $json = json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);



        if ($json === false) {

            flock($handle, LOCK_UN);

            fclose($handle);

            return false;

        }



        ftruncate($handle, 0);

        rewind($handle);

        fwrite($handle, $json);

        fflush($handle);

        flock($handle, LOCK_UN);

        fclose($handle);



        @chmod($path, 0640);

        return true;

    }



    public function delete(string $store): bool

    {

        $path = $this->path($store);

        if (!is_file($path)) {

            return true;

        }



        return @unlink($path);

    }



    public function exists(string $store): bool

    {

        return is_file($this->path($store));

    }



    /** Absolute filesystem path for a store (JSON driver only). */

    public function path(string $store): string

    {

        $normalized = StorageKeys::normalize($store);

        $normalized = ltrim(str_replace(['..', '\\'], ['', '/'], $normalized), '/');



        return $this->basePath . '/' . StorageKeys::toJsonFilename($normalized);

    }



    private function ensureDirectory(string $dir): void

    {

        if (!is_dir($dir)) {

            mkdir($dir, 0750, true);

        }

    }

}


