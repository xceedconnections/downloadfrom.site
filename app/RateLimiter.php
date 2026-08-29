<?php

declare(strict_types=1);

namespace App;

use App\Contracts\StorageInterface;
use App\Storage\StorageKeys;

class RateLimiter
{
    private StorageInterface $db;
    private bool $enabled;
    private int $perMinute;
    private int $perDay;

    public function __construct(StorageInterface $db, array $config)
    {
        $this->db = $db;
        $this->enabled = (bool) ($config['rate_limit']['enabled'] ?? true);
        $this->perMinute = (int) ($config['rate_limit']['requests_per_minute'] ?? 10);
        $this->perDay = (int) ($config['rate_limit']['requests_per_day'] ?? 100);
    }

    public function check(string $identifier, string $type = 'api'): array
    {
        if (!$this->enabled) {
            return ['allowed' => true];
        }

        $key = hash('sha256', $type . '|' . $identifier);
        $now = time();
        $minuteWindow = $now - 60;
        $dayWindow = $now - 86400;

        $data = $this->db->read(StorageKeys::RATE_LIMITS, []);

        if (!isset($data[$key])) {
            $data[$key] = ['requests' => []];
        }

        $data[$key]['requests'] = array_values(array_filter(
            $data[$key]['requests'],
            static fn(int $ts): bool => $ts > $dayWindow
        ));

        $minuteCount = count(array_filter(
            $data[$key]['requests'],
            static fn(int $ts): bool => $ts > $minuteWindow
        ));

        $dayCount = count($data[$key]['requests']);

        if ($minuteCount >= $this->perMinute) {
            return [
                'allowed' => false,
                'reason' => 'Too many requests. Please wait a minute and try again.',
                'retry_after' => 60,
            ];
        }

        if ($dayCount >= $this->perDay) {
            return [
                'allowed' => false,
                'reason' => 'Daily request limit exceeded. Please try again tomorrow.',
                'retry_after' => 86400,
            ];
        }

        $data[$key]['requests'][] = $now;
        $this->db->write(StorageKeys::RATE_LIMITS, $data);

        return ['allowed' => true, 'remaining_minute' => $this->perMinute - $minuteCount - 1];
    }

    public function getStats(): array
    {
        $data = $this->db->read(StorageKeys::RATE_LIMITS, []);
        $now = time();
        $active = 0;
        $totalRequests = 0;

        foreach ($data as $entry) {
            $recent = array_filter($entry['requests'] ?? [], static fn(int $ts): bool => $ts > $now - 86400);
            if (count($recent) > 0) {
                $active++;
                $totalRequests += count($recent);
            }
        }

        return [
            'active_identifiers' => $active,
            'requests_24h' => $totalRequests,
            'limits' => [
                'per_minute' => $this->perMinute,
                'per_day' => $this->perDay,
            ],
        ];
    }
}
