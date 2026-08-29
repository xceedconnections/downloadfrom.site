<?php

declare(strict_types=1);

namespace App;

use App\Contracts\StorageInterface;
use App\Storage\StorageKeys;

class Analytics
{
    private StorageInterface $db;
    private bool $enabled;

    public function __construct(StorageInterface $db, array $config)
    {
        $this->db = $db;
        $this->enabled = (bool) ($config['analytics']['enabled'] ?? true);
    }

    public function record(string $platform, bool $success, float $responseTimeMs): void
    {
        if (!$this->enabled) {
            return;
        }

        $date = date('Y-m-d');
        $this->db->update(StorageKeys::ANALYTICS, function (array $data) use ($date, $platform, $success, $responseTimeMs): array {
            if (!isset($data[$date])) {
                $data[$date] = [
                    'total' => 0,
                    'success' => 0,
                    'failed' => 0,
                    'platforms' => [],
                    'avg_response_ms' => 0,
                    '_response_sum' => 0,
                ];
            }

            $data[$date]['total']++;
            if ($success) {
                $data[$date]['success']++;
            } else {
                $data[$date]['failed']++;
            }

            if (!isset($data[$date]['platforms'][$platform])) {
                $data[$date]['platforms'][$platform] = 0;
            }
            $data[$date]['platforms'][$platform]++;

            $data[$date]['_response_sum'] += $responseTimeMs;
            $data[$date]['avg_response_ms'] = round(
                $data[$date]['_response_sum'] / $data[$date]['total'],
                2
            );

            return $data;
        }, []);
    }

    public function getSummary(int $days = 7): array
    {
        $data = $this->db->read(StorageKeys::ANALYTICS, []);
        $summary = ['total' => 0, 'success' => 0, 'failed' => 0, 'days' => []];

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            if (isset($data[$date])) {
                $day = $data[$date];
                unset($day['_response_sum']);
                $summary['days'][$date] = $day;
                $summary['total'] += $day['total'];
                $summary['success'] += $day['success'];
                $summary['failed'] += $day['failed'];
            }
        }

        return $summary;
    }
}
