<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DownloadSessionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string, mixed> $data */
    public function store(string $token, array $data, int $ttl): bool
    {
        $created = time();
        $expires = $created + $ttl;
        $payload = json_encode([
            'data' => $data,
            'created' => $created,
            'expires' => $expires,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO download_sessions (token, payload, created_at, expires_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), created_at = VALUES(created_at), expires_at = VALUES(expires_at)'
        );

        return $stmt->execute([$token, $payload, $created, $expires]);
    }

    /** @return array<string, mixed>|null */
    public function get(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT payload, expires_at FROM download_sessions WHERE token = ? LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        if (time() > (int) ($row['expires_at'] ?? 0)) {
            $this->delete($token);
            return null;
        }

        $decoded = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($decoded)) {
            return null;
        }

        $data = $decoded['data'] ?? null;
        return is_array($data) ? $data : null;
    }

    public function delete(string $token): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM download_sessions WHERE token = ?');
        return $stmt->execute([$token]);
    }

    /** @return array<string, mixed>|null */
    public function take(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT payload FROM download_sessions WHERE token = ? LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $this->delete($token);
        $decoded = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($decoded)) {
            return null;
        }

        $data = $decoded['data'] ?? null;
        return is_array($data) ? $data : null;
    }

    public function exists(string $token): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM download_sessions WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
        return (bool) $stmt->fetchColumn();
    }
}
