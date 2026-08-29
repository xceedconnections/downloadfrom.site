<?php

declare(strict_types=1);

namespace App\Storage;

use App\Contracts\StorageInterface;
use App\Logger;
use PDO;
use PDOException;
use Throwable;

class MysqlStorage implements StorageInterface
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $mysql = $config['storage']['mysql'] ?? [];
        $host = (string) ($mysql['host'] ?? '127.0.0.1');
        $port = (int) ($mysql['port'] ?? 3306);
        $database = (string) ($mysql['database'] ?? '');
        $username = (string) ($mysql['username'] ?? '');
        $password = (string) ($mysql['password'] ?? '');

        if ($database === '') {
            throw new PDOException('storage.mysql.database is required when storage.driver is mysql');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        $this->pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function read(string $store, mixed $default = []): mixed
    {
        $key = StorageKeys::normalize($store);

        try {
            $stmt = $this->pdo->prepare('SELECT payload FROM app_storage WHERE store_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
        } catch (PDOException $e) {
            Logger::error('MySQL read failed for ' . $key . ': ' . $e->getMessage());
            return $default;
        }

        if (!$row || !isset($row['payload'])) {
            return $default;
        }

        $data = json_decode((string) $row['payload'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::error('MySQL JSON decode failed for ' . $key . ': ' . json_last_error_msg());
            return $default;
        }

        return $data;
    }

    public function write(string $store, mixed $data): bool
    {
        $key = StorageKeys::normalize($store);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO app_storage (store_key, payload)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = CURRENT_TIMESTAMP'
            );

            return $stmt->execute([$key, $json]);
        } catch (PDOException $e) {
            Logger::error('MySQL write failed for ' . $key . ': ' . $e->getMessage());
            return false;
        }
    }

    public function update(string $store, callable $callback, mixed $default = []): bool
    {
        $key = StorageKeys::normalize($store);

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('SELECT payload FROM app_storage WHERE store_key = ? FOR UPDATE');
            $stmt->execute([$key]);
            $row = $stmt->fetch();

            $current = $default;
            if ($row && isset($row['payload'])) {
                $decoded = json_decode((string) $row['payload'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $current = $decoded;
                } else {
                    $this->pdo->rollBack();
                    return false;
                }
            }

            $updated = $callback($current ?? $default);
            $json = json_encode($updated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                $this->pdo->rollBack();
                return false;
            }

            $upsert = $this->pdo->prepare(
                'INSERT INTO app_storage (store_key, payload)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = CURRENT_TIMESTAMP'
            );
            $upsert->execute([$key, $json]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::error('MySQL update failed for ' . $key . ': ' . $e->getMessage());
            return false;
        }
    }

    public function delete(string $store): bool
    {
        $key = StorageKeys::normalize($store);

        try {
            $stmt = $this->pdo->prepare('DELETE FROM app_storage WHERE store_key = ?');
            return $stmt->execute([$key]);
        } catch (PDOException $e) {
            Logger::error('MySQL delete failed for ' . $key . ': ' . $e->getMessage());
            return false;
        }
    }

    public function exists(string $store): bool
    {
        $key = StorageKeys::normalize($store);

        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM app_storage WHERE store_key = ? LIMIT 1');
            $stmt->execute([$key]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            Logger::error('MySQL exists check failed for ' . $key . ': ' . $e->getMessage());
            return false;
        }
    }
}
