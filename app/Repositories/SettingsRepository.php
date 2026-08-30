<?php

declare(strict_types=1);

namespace App\Repositories;

use App\ServiceConfig;
use PDO;

final class SettingsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function isEmpty(): bool
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM site_settings');
        return (int) ($stmt?->fetchColumn() ?: 0) === 0;
    }

    /** @return array<string, mixed> */
    public function loadAll(array $defaults): array
    {
        $settings = $defaults;

        $stmt = $this->pdo->query('SELECT setting_key, setting_value FROM site_settings');
        $rows = $stmt ? $stmt->fetchAll() : [];
        foreach ($rows as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            $value = (string) ($row['setting_value'] ?? '');
            match ($key) {
                'analytics_enabled', 'maintenance_mode', 'ads_block_adblock' => $settings[$key] = $value === '1',
                default => $settings[$key] = $value,
            };
        }

        $settings['custom_codes'] = [
            'head' => (string) ($settings['custom_codes_head'] ?? ''),
            'body_end' => (string) ($settings['custom_codes_body_end'] ?? ''),
        ];
        unset($settings['custom_codes_head'], $settings['custom_codes_body_end']);

        $settings['services'] = $this->loadServices();
        $settings['providers'] = $this->loadProviders('video');
        $settings['audio_providers'] = $this->loadProviders('audio');

        return $settings;
    }

    /** @param array<string, mixed> $settings */
    public function saveAll(array $settings, array $defaults): bool
    {
        $merged = array_replace_recursive($defaults, $settings);

        try {
            $this->pdo->beginTransaction();

            $this->pdo->exec('DELETE FROM site_settings');
            $insert = $this->pdo->prepare(
                'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)'
            );

            $scalarKeys = [
                'site_name', 'site_url', 'admin_email', 'footer_text', 'logo_path',
            ];
            foreach ($scalarKeys as $key) {
                $insert->execute([$key, (string) ($merged[$key] ?? '')]);
            }
            $insert->execute(['analytics_enabled', !empty($merged['analytics_enabled']) ? '1' : '0']);
            $insert->execute(['maintenance_mode', !empty($merged['maintenance_mode']) ? '1' : '0']);
            $insert->execute(['ads_block_adblock', !empty($merged['ads_block_adblock']) ? '1' : '0']);

            $codes = $merged['custom_codes'] ?? [];
            $insert->execute(['custom_codes_head', (string) ($codes['head'] ?? '')]);
            $insert->execute(['custom_codes_body_end', (string) ($codes['body_end'] ?? '')]);

            $this->saveServices($merged['services'] ?? ServiceConfig::defaultServices());
            $this->saveProviders('video', $merged['providers'] ?? []);
            $this->saveProviders('audio', $merged['audio_providers'] ?? []);

            $this->pdo->commit();
            return true;
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    /** @param array<string, mixed> $data */
    public function importFromLegacy(array $data, array $defaults): void
    {
        $this->saveAll($data, $defaults);
    }

    /** @return array<string, array<string, mixed>> */
    private function loadServices(): array
    {
        $services = ServiceConfig::defaultServices();
        $stmt = $this->pdo->query('SELECT service_id, enabled, name, nav_label FROM services');
        foreach ($stmt ? $stmt->fetchAll() : [] as $row) {
            $id = (string) ($row['service_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $services[$id] = [
                'enabled' => (bool) ($row['enabled'] ?? true),
                'name' => (string) ($row['name'] ?? ''),
                'nav_label' => (string) ($row['nav_label'] ?? ''),
                'providers' => [],
            ];
        }

        $linkStmt = $this->pdo->query(
            'SELECT service_id, provider_id, provider_type FROM service_providers ORDER BY service_id, provider_id'
        );
        foreach ($linkStmt ? $linkStmt->fetchAll() : [] as $row) {
            $serviceId = (string) ($row['service_id'] ?? '');
            if (!isset($services[$serviceId])) {
                continue;
            }
            $services[$serviceId]['providers'][] = (string) ($row['provider_id'] ?? '');
        }

        return $services;
    }

    /** @param array<string, array<string, mixed>> $services */
    private function saveServices(array $services): void
    {
        $this->pdo->exec('DELETE FROM service_providers');
        $this->pdo->exec('DELETE FROM services');

        $insert = $this->pdo->prepare(
            'INSERT INTO services (service_id, enabled, name, nav_label) VALUES (?, ?, ?, ?)'
        );
        $link = $this->pdo->prepare(
            'INSERT INTO service_providers (service_id, provider_id, provider_type) VALUES (?, ?, ?)'
        );

        foreach ($services as $serviceId => $service) {
            if (!is_array($service)) {
                continue;
            }
            $insert->execute([
                (string) $serviceId,
                !empty($service['enabled']) ? 1 : 0,
                (string) ($service['name'] ?? ''),
                (string) ($service['nav_label'] ?? ''),
            ]);

            $type = $serviceId === ServiceConfig::SERVICE_AUDIO ? 'audio' : 'video';
            foreach ($service['providers'] ?? [] as $providerId) {
                if (is_string($providerId) && $providerId !== '') {
                    $link->execute([(string) $serviceId, $providerId, $type]);
                }
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function loadProviders(string $type): array
    {
        $table = $type === 'audio' ? 'audio_providers' : 'video_providers';
        $stmt = $this->pdo->query(
            "SELECT * FROM {$table} ORDER BY sort_order ASC, provider_id ASC"
        );
        $providers = [];
        foreach ($stmt ? $stmt->fetchAll() : [] as $row) {
            $id = (string) ($row['provider_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $blocked = json_decode((string) ($row['blocked_channels'] ?? '[]'), true);
            $providers[$id] = [
                'enabled' => (bool) ($row['enabled'] ?? false),
                'show_as_new' => (bool) ($row['show_as_new'] ?? false),
                'proxy_enabled' => (bool) ($row['proxy_enabled'] ?? false),
                'title' => (string) ($row['title'] ?? ''),
                'h1' => (string) ($row['h1'] ?? ''),
                'meta_description' => (string) ($row['meta_description'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'keywords' => (string) ($row['keywords'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'blocked_channels' => is_array($blocked) ? $blocked : [],
            ];
        }

        return $providers;
    }

    /** @param array<string, array<string, mixed>> $providers */
    private function saveProviders(string $type, array $providers): void
    {
        $table = $type === 'audio' ? 'audio_providers' : 'video_providers';
        $this->pdo->exec("DELETE FROM {$table}");

        $insert = $this->pdo->prepare(
            "INSERT INTO {$table}
            (provider_id, enabled, show_as_new, proxy_enabled, title, h1, meta_description, description, keywords, slug, blocked_channels, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $order = 0;
        foreach ($providers as $providerId => $provider) {
            if (!is_array($provider)) {
                continue;
            }
            $insert->execute([
                (string) $providerId,
                !empty($provider['enabled']) ? 1 : 0,
                !empty($provider['show_as_new']) ? 1 : 0,
                !empty($provider['proxy_enabled']) ? 1 : 0,
                (string) ($provider['title'] ?? ''),
                (string) ($provider['h1'] ?? ''),
                (string) ($provider['meta_description'] ?? ''),
                (string) ($provider['description'] ?? ''),
                (string) ($provider['keywords'] ?? ''),
                (string) ($provider['slug'] ?? ''),
                json_encode($provider['blocked_channels'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $order++,
            ]);
        }
    }
}
