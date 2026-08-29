<?php

declare(strict_types=1);

namespace App;

use App\Contracts\StorageInterface;
use App\Repositories\AnalyticsRepository;
use App\Storage\DatabaseConnection;

class Analytics
{
    private AnalyticsRepository $repo;
    private bool $enabled;

    public function __construct(StorageInterface $db, array $config)
    {
        $this->repo = new AnalyticsRepository(DatabaseConnection::get());
        $this->enabled = (bool) ($config['analytics']['enabled'] ?? true);
    }

    public function record(string $platform, bool $success, float $responseTimeMs): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->repo->record(date('Y-m-d'), $platform, $success, $responseTimeMs);
    }

    public function getSummary(int $days = 7): array
    {
        return $this->repo->getSummary($days);
    }
}
