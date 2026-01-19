<?php

declare(strict_types=1);

namespace Zenmanage\Cache;

/**
 * No-op cache implementation for testing or when caching is disabled.
 */
final class NullCache implements CacheInterface
{
    public function get(string $key): ?string
    {
        return null;
    }

    public function set(string $key, string $value, ?int $ttl = null): void
    {
        // No-op
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function delete(string $key): void
    {
        // No-op
    }

    public function clear(): void
    {
        // No-op
    }
}
