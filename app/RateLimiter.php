<?php

declare(strict_types=1);

namespace App;

use App\Contracts\StorageInterface;
use App\Repositories\RateLimitRepository;
use App\Storage\DatabaseConnection;

class RateLimiter
{
    private RateLimitRepository $repo;
    private bool $enabled;
    private int $perMinute;
    private int $perDay;

    public function __construct(StorageInterface $db, array $config)
    {
        $this->repo = new RateLimitRepository(DatabaseConnection::get());
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
        $result = $this->repo->check($key, $this->perMinute, $this->perDay);
        if ($result['allowed']) {
            $result['remaining_minute'] = $result['remaining_minute'] ?? 0;
        }

        return $result;
    }

    public function getStats(): array
    {
        $stats = $this->repo->getStats();

        return [
            'active_identifiers' => $stats['active_identifiers'],
            'requests_24h' => $stats['requests_24h'],
            'limits' => [
                'per_minute' => $this->perMinute,
                'per_day' => $this->perDay,
            ],
        ];
    }
}
