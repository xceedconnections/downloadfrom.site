<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AnalyticsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(string $date, string $platform, bool $success, float $responseTimeMs): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'INSERT INTO analytics_daily (stat_date, total, success, failed, response_sum, avg_response_ms)
                 VALUES (?, 1, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                 total = total + 1,
                 success = success + VALUES(success),
                 failed = failed + VALUES(failed),
                 response_sum = response_sum + VALUES(response_sum),
                 avg_response_ms = (response_sum + VALUES(response_sum)) / (total + 1)'
            );
            $stmt->execute([
                $date,
                $success ? 1 : 0,
                $success ? 0 : 1,
                $responseTimeMs,
                $responseTimeMs,
            ]);

            $platformStmt = $this->pdo->prepare(
                'INSERT INTO analytics_platform_daily (stat_date, platform, request_count)
                 VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE request_count = request_count + 1'
            );
            $platformStmt->execute([$date, $platform]);

            $this->pdo->commit();
            return true;
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    /** @return array{total: int, success: int, failed: int, days: array<string, array<string, mixed>>} */
    public function getSummary(int $days = 7): array
    {
        $summary = ['total' => 0, 'success' => 0, 'failed' => 0, 'days' => []];

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $stmt = $this->pdo->prepare(
                'SELECT total, success, failed, avg_response_ms FROM analytics_daily WHERE stat_date = ? LIMIT 1'
            );
            $stmt->execute([$date]);
            $row = $stmt->fetch();
            if (!$row) {
                continue;
            }

            $platformStmt = $this->pdo->prepare(
                'SELECT platform, request_count FROM analytics_platform_daily WHERE stat_date = ?'
            );
            $platformStmt->execute([$date]);
            $platforms = [];
            foreach ($platformStmt->fetchAll() as $platformRow) {
                $platforms[(string) ($platformRow['platform'] ?? '')] = (int) ($platformRow['request_count'] ?? 0);
            }

            $day = [
                'total' => (int) ($row['total'] ?? 0),
                'success' => (int) ($row['success'] ?? 0),
                'failed' => (int) ($row['failed'] ?? 0),
                'avg_response_ms' => (float) ($row['avg_response_ms'] ?? 0),
                'platforms' => $platforms,
            ];
            $summary['days'][$date] = $day;
            $summary['total'] += $day['total'];
            $summary['success'] += $day['success'];
            $summary['failed'] += $day['failed'];
        }

        return $summary;
    }

    /** @param array<string, array<string, mixed>> $legacy */
    public function importFromLegacy(array $legacy): void
    {
        foreach ($legacy as $date => $day) {
            if (!is_string($date) || !is_array($day)) {
                continue;
            }
            $this->pdo->prepare(
                'INSERT INTO analytics_daily (stat_date, total, success, failed, response_sum, avg_response_ms)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                 total = VALUES(total), success = VALUES(success), failed = VALUES(failed),
                 response_sum = VALUES(response_sum), avg_response_ms = VALUES(avg_response_ms)'
            )->execute([
                $date,
                (int) ($day['total'] ?? 0),
                (int) ($day['success'] ?? 0),
                (int) ($day['failed'] ?? 0),
                (float) ($day['_response_sum'] ?? 0),
                (float) ($day['avg_response_ms'] ?? 0),
            ]);

            foreach ($day['platforms'] ?? [] as $platform => $count) {
                if (!is_string($platform)) {
                    continue;
                }
                $this->pdo->prepare(
                    'INSERT INTO analytics_platform_daily (stat_date, platform, request_count)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE request_count = VALUES(request_count)'
                )->execute([$date, $platform, (int) $count]);
            }
        }
    }
}
