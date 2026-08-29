<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RateLimitRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{allowed: bool, reason?: string, retry_after?: int, remaining_minute?: int} */
    public function check(string $bucketHash, int $perMinute, int $perDay): array
    {
        $now = time();
        $minuteWindow = $now - 60;
        $dayWindow = $now - 86400;

        $this->purgeOlderThan($dayWindow);

        $minuteStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rate_limit_events WHERE bucket_hash = ? AND requested_at > ?'
        );
        $minuteStmt->execute([$bucketHash, $minuteWindow]);
        $minuteCount = (int) ($minuteStmt->fetchColumn() ?: 0);

        $dayStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rate_limit_events WHERE bucket_hash = ? AND requested_at > ?'
        );
        $dayStmt->execute([$bucketHash, $dayWindow]);
        $dayCount = (int) ($dayStmt->fetchColumn() ?: 0);

        if ($minuteCount >= $perMinute) {
            return [
                'allowed' => false,
                'reason' => 'Too many requests. Please wait a minute and try again.',
                'retry_after' => 60,
            ];
        }

        if ($dayCount >= $perDay) {
            return [
                'allowed' => false,
                'reason' => 'Daily request limit exceeded. Please try again tomorrow.',
                'retry_after' => 86400,
            ];
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO rate_limit_events (bucket_hash, requested_at) VALUES (?, ?)'
        );
        $insert->execute([$bucketHash, $now]);

        return ['allowed' => true, 'remaining_minute' => $perMinute - $minuteCount - 1];
    }

    /** @return array{active_identifiers: int, requests_24h: int} */
    public function getStats(): array
    {
        $since = time() - 86400;
        $stmt = $this->pdo->prepare(
            'SELECT bucket_hash, COUNT(*) AS cnt FROM rate_limit_events WHERE requested_at > ? GROUP BY bucket_hash'
        );
        $stmt->execute([$since]);

        $active = 0;
        $total = 0;
        foreach ($stmt->fetchAll() as $row) {
            $count = (int) ($row['cnt'] ?? 0);
            if ($count > 0) {
                $active++;
                $total += $count;
            }
        }

        return ['active_identifiers' => $active, 'requests_24h' => $total];
    }

    private function purgeOlderThan(int $timestamp): void
    {
        $this->pdo->prepare('DELETE FROM rate_limit_events WHERE requested_at <= ?')->execute([$timestamp]);
    }

    /** @param array<string, array{requests?: array<int, int>}> $legacy */
    public function importFromLegacy(array $legacy): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO rate_limit_events (bucket_hash, requested_at) VALUES (?, ?)'
        );
        foreach ($legacy as $hash => $entry) {
            if (!is_string($hash) || !is_array($entry)) {
                continue;
            }
            foreach ($entry['requests'] ?? [] as $requestedAt) {
                if (is_int($requestedAt)) {
                    $insert->execute([$hash, $requestedAt]);
                }
            }
        }
    }
}
