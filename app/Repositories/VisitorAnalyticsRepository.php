<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class VisitorAnalyticsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO visitor_events
            (session_key, ip_address, country_code, country_name, page_url, page_path, page_title,
             referrer_url, referrer_source, user_agent, browser, os_name, device_type, duration_seconds, visited_at, left_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0)'
        );
        $visitedAt = (int) ($data['visited_at'] ?? time());
        $stmt->execute([
            (string) ($data['session_key'] ?? ''),
            (string) ($data['ip_address'] ?? ''),
            (string) ($data['country_code'] ?? ''),
            (string) ($data['country_name'] ?? ''),
            (string) ($data['page_url'] ?? ''),
            (string) ($data['page_path'] ?? ''),
            (string) ($data['page_title'] ?? ''),
            (string) ($data['referrer_url'] ?? ''),
            (string) ($data['referrer_source'] ?? ''),
            (string) ($data['user_agent'] ?? ''),
            (string) ($data['browser'] ?? ''),
            (string) ($data['os_name'] ?? ''),
            (string) ($data['device_type'] ?? 'desktop'),
            $visitedAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateDuration(int $id, int $durationSeconds, int $leftAt): bool
    {
        if ($id <= 0) {
            return false;
        }

        $durationSeconds = max(0, min(86400, $durationSeconds));
        $stmt = $this->pdo->prepare(
            'UPDATE visitor_events SET duration_seconds = ?, left_at = ? WHERE id = ?'
        );

        return $stmt->execute([$durationSeconds, $leftAt, $id]);
    }

    private function junkSql(): string
    {
        return " AND page_path NOT IN ('/favicon.ico', '/robots.txt', '/sitemap.xml')
                 AND page_path NOT LIKE '/open%'
                 AND page_path NOT LIKE '/assets/%'
                 AND page_path NOT LIKE '%.%' ";
    }

    public function countSince(int $since): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM visitor_events WHERE visited_at >= ?' . $this->junkSql());
        $stmt->execute([$since]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function listSince(int $since, int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM visitor_events WHERE visited_at >= ?' . $this->junkSql()
            . ' ORDER BY visited_at DESC, id DESC LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $since, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
        $stmt->bindValue(3, max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string, mixed> */
    public function summarySince(int $since): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS total_visits,
                COUNT(DISTINCT ip_address) AS unique_ips,
                COUNT(DISTINCT session_key) AS unique_sessions,
                AVG(NULLIF(duration_seconds, 0)) AS avg_duration,
                MAX(duration_seconds) AS max_duration
             FROM visitor_events
             WHERE visited_at >= ?' . $this->junkSql()
        );
        $stmt->execute([$since]);
        $row = $stmt->fetch() ?: [];

        $countries = $this->topGroupedSince($since, 'country_code', 'country_name', 8);
        $pages = $this->topGroupedSince($since, 'page_title', 'page_title', 8, " AND page_title <> '' ");
        $referrers = $this->topGroupedSince($since, 'referrer_source', 'referrer_source', 8, " AND referrer_source <> '' ");
        $browsers = $this->topGroupedSince($since, 'browser', 'browser', 8);

        return [
            'total_visits' => (int) ($row['total_visits'] ?? 0),
            'unique_ips' => (int) ($row['unique_ips'] ?? 0),
            'unique_sessions' => (int) ($row['unique_sessions'] ?? 0),
            'avg_duration' => round((float) ($row['avg_duration'] ?? 0), 1),
            'max_duration' => (int) ($row['max_duration'] ?? 0),
            'top_countries' => $countries,
            'top_pages' => $pages,
            'top_referrers' => $referrers,
            'top_browsers' => $browsers,
        ];
    }

    /** @return array<int, array{label: string, count: int}> */
    private function topGroupedSince(int $since, string $keyCol, string $labelCol, int $limit, string $extraWhere = ''): array
    {
        $allowed = ['country_code' => 1, 'page_path' => 1, 'page_title' => 1, 'referrer_source' => 1, 'browser' => 1];
        if (!isset($allowed[$keyCol]) || !isset($allowed[$labelCol])) {
            return [];
        }

        $sql = sprintf(
            'SELECT %s AS grp_key, %s AS grp_label, COUNT(*) AS cnt
             FROM visitor_events
             WHERE visited_at >= ? %s AND %s <> \'\'
             GROUP BY grp_key, grp_label
             ORDER BY cnt DESC
             LIMIT %d',
            $keyCol,
            $labelCol,
            $this->junkSql() . $extraWhere,
            $keyCol,
            max(1, $limit)
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$since]);
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'label' => (string) ($row['grp_label'] ?? ''),
                'code' => $keyCol === 'country_code' ? (string) ($row['grp_key'] ?? '') : '',
                'count' => (int) ($row['cnt'] ?? 0),
            ];
        }

        return $rows;
    }

    public function clearAll(): int
    {
        $count = (int) ($this->pdo->query('SELECT COUNT(*) FROM visitor_events')?->fetchColumn() ?: 0);
        $this->pdo->exec('TRUNCATE TABLE visitor_events');

        return $count;
    }
}
