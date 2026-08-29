<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AdminRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function isEmpty(): bool
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM admin_users');
        return (int) ($stmt?->fetchColumn() ?: 0) === 0;
    }

    /** @return array{username?: string, password_hash?: string, created?: string} */
    public function loadPrimary(): array
    {
        $stmt = $this->pdo->query(
            'SELECT username, password_hash, created_at FROM admin_users ORDER BY id ASC LIMIT 1'
        );
        $row = $stmt ? $stmt->fetch() : false;
        if (!$row) {
            return [];
        }

        return [
            'username' => (string) ($row['username'] ?? 'admin'),
            'password_hash' => (string) ($row['password_hash'] ?? ''),
            'created' => (string) ($row['created_at'] ?? ''),
        ];
    }

    public function createDefault(string $username, string $passwordHash): bool
    {
        if (!$this->isEmpty()) {
            return true;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_users (username, password_hash) VALUES (?, ?)'
        );

        return $stmt->execute([$username, $passwordHash]);
    }

    public function updatePassword(string $passwordHash): bool
    {
        $admin = $this->loadPrimary();
        if ($admin === []) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE admin_users SET password_hash = ? WHERE username = ? LIMIT 1'
        );

        return $stmt->execute([$passwordHash, (string) ($admin['username'] ?? 'admin')]);
    }

    /** @param array{username?: string, password_hash?: string, created?: string} $data */
    public function importFromLegacy(array $data): void
    {
        if ($this->isEmpty()) {
            $this->createDefault(
                (string) ($data['username'] ?? 'admin'),
                (string) ($data['password_hash'] ?? password_hash('changeme123', PASSWORD_DEFAULT))
            );
        }
    }
}
