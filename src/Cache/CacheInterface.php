<?php

declare(strict_types=1);

namespace Zenmanage\Cache;

/**
 * Interface for cache implementations.
 */
interface CacheInterface
{
    /**
     * Retrieve a value from the cache.
     */
    public function get(string $key): ?string;

    /**
     * Store a value in the cache with optional TTL.
     *
     * @param int|null $ttl Time to live in seconds, null for no expiration
     */
    public function set(string $key, string $value, ?int $ttl = null): void;

    /**
     * Check if a key exists in the cache.
     */
    public function has(string $key): bool;

    /**
     * Delete a value from the cache.
     */
    public function delete(string $key): void;

    /**
     * Clear all values from the cache.
     */
    public function clear(): void;
}
