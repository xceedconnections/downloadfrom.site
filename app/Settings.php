<?php

declare(strict_types=1);

namespace App;

use App\Contracts\StorageInterface;
use App\Repositories\SettingsRepository;
use App\Storage\DatabaseConnection;

class Settings
{
    /**
     * Site-wide settings persisted in MySQL relational tables.
     */
    private SettingsRepository $repo;
    private ?array $cache = null;

    public function __construct(StorageInterface $db)
    {
        $this->repo = new SettingsRepository(DatabaseConnection::get());
    }

    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = $this->repo->loadAll($this->defaults());
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
        return $this->repo->saveAll($all, $this->defaults());
    }

    public function save(array $settings): bool
    {
        $this->cache = array_replace_recursive($this->defaults(), $settings);
        return $this->repo->saveAll($this->cache, $this->defaults());
    }

    private function defaults(): array
    {
        return [
            'site_name' => 'VideoLink',
            'site_url' => 'http://localhost/downloadfrom',
            'analytics_enabled' => true,
            'maintenance_mode' => false,
            'ads_block_adblock' => false,
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
