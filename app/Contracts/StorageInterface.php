<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Application data storage contract.
 *
 * Uses logical store keys (e.g. "settings", "faq", "results/{token}") so the
 * same code works with JSON files today and MySQL tomorrow.
 */
interface StorageInterface
{
    /** @param mixed $default Returned when the store does not exist or is invalid */
    public function read(string $store, mixed $default = []): mixed;

    public function write(string $store, mixed $data): bool;

    /**
     * Atomically read, transform, and write a store.
     *
     * @param callable(mixed): mixed $callback
     */
    public function update(string $store, callable $callback, mixed $default = []): bool;

    public function delete(string $store): bool;

    public function exists(string $store): bool;
}
