<?php

declare(strict_types=1);

namespace App;

use App\Contracts\StorageInterface;
use App\Storage\StorageKeys;

class Settings
{
    private StorageInterface $db;
    private ?array $cache = null;

    public function __construct(StorageInterface $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = $this->db->read(StorageKeys::SETTINGS, $this->defaults());
        }
        return $this->cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        $keys = explode('.', $key);
        $value = $all;
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }

    public function set(string $key, mixed $value): bool
    {
        $all = $this->all();
        $keys = explode('.', $key);
        $ref = &$all;
        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $ref[$k] = $value;
            } else {
                if (!isset($ref[$k]) || !is_array($ref[$k])) {
                    $ref[$k] = [];
                }
                $ref = &$ref[$k];
            }
        }
        $this->cache = $all;
        return $this->db->write(StorageKeys::SETTINGS, $all);
    }

    public function save(array $settings): bool
    {
        $this->cache = array_replace_recursive($this->defaults(), $settings);
        return $this->db->write(StorageKeys::SETTINGS, $this->cache);
    }

    private function defaults(): array
    {
        return [
            'site_name' => 'VideoLink',
            'site_url' => 'http://localhost/downloadfrom',
            'analytics_enabled' => true,
            'maintenance_mode' => false,
            'admin_email' => 'admin@example.com',
            'footer_text' => 'Free online video URL tool for supported platforms. Retrieve public metadata and permitted viewing options.',
            'logo_path' => '',
            'custom_codes' => [
                'head' => '',
                'body_end' => '',
            ],
            'services' => ServiceConfig::defaultServices(),
            'providers' => [],
            'audio_providers' => [],
        ];
    }
}
