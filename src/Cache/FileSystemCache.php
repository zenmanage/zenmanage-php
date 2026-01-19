<?php

declare(strict_types=1);

namespace Zenmanage\Cache;

use Zenmanage\Exception\CacheException;

/**
 * File-based cache implementation with TTL support.
 */
final class FileSystemCache implements CacheInterface
{
    private const FILE_EXTENSION = '.cache';

    public function __construct(
        private readonly string $cacheDirectory,
    ) {
        $this->ensureDirectoryExists();
    }

    public function get(string $key): ?string
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $content = @file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        $data = @json_decode($content, true);

        if (!is_array($data) || !isset($data['value'], $data['expires'])) {
            return null;
        }

        // Check if expired
        if ($data['expires'] !== null && $data['expires'] < time()) {
            @unlink($filePath);

            return null;
        }

        return $data['value'];
    }

    public function set(string $key, string $value, ?int $ttl = null): void
    {
        $expires = $ttl !== null ? time() + $ttl : null;

        $data = [
            'value' => $value,
            'expires' => $expires,
        ];

        $filePath = $this->getFilePath($key);
        $content = json_encode($data);

        if ($content === false) {
            throw new CacheException("Failed to encode cache data for key: {$key}");
        }

        $result = @file_put_contents($filePath, $content, LOCK_EX);

        if ($result === false) {
            throw new CacheException("Failed to write cache file: {$filePath}");
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): void
    {
        $filePath = $this->getFilePath($key);

        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    public function clear(): void
    {
        $files = glob($this->cacheDirectory . '/*' . self::FILE_EXTENSION);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            @unlink($file);
        }
    }

    private function getFilePath(string $key): string
    {
        $hash = md5($key);

        return $this->cacheDirectory . '/' . $hash . self::FILE_EXTENSION;
    }

    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->cacheDirectory)) {
            $result = @mkdir($this->cacheDirectory, 0755, true);

            if (!$result) {
                throw new CacheException("Failed to create cache directory: {$this->cacheDirectory}");
            }
        }

        if (!is_writable($this->cacheDirectory)) {
            throw new CacheException("Cache directory is not writable: {$this->cacheDirectory}");
        }
    }
}
