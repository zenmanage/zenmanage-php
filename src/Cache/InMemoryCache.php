<?php

declare(strict_types=1);

namespace Zenmanage\Cache;

/**
 * In-memory cache implementation (data persists only for current request).
 */
final class InMemoryCache implements CacheInterface
{
    /** @var array<string, array{value: string, expires: int|null}> */
    private array $storage = [];

    public function get(string $key): ?string
    {
        if (!isset($this->storage[$key])) {
            return null;
        }

        $item = $this->storage[$key];

        // Check if expired
        if ($item['expires'] !== null && $item['expires'] < time()) {
            unset($this->storage[$key]);

            return null;
        }

        return $item['value'];
    }

    public function set(string $key, string $value, ?int $ttl = null): void
    {
        $expires = $ttl !== null ? time() + $ttl : null;

        $this->storage[$key] = [
            'value' => $value,
            'expires' => $expires,
        ];
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): void
    {
        unset($this->storage[$key]);
    }

    public function clear(): void
    {
        $this->storage = [];
    }
}
