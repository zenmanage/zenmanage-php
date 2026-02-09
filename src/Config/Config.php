<?php

declare(strict_types=1);

namespace Zenmanage\Config;

use Zenmanage\Exception\ConfigurationException;

/**
 * Immutable configuration object for the SDK.
 */
final class Config
{
    private const DEFAULT_CACHE_TTL = 3600; // 1 hour
    private const DEFAULT_CACHE_BACKEND = 'memory';
    private const DEFAULT_API_ENDPOINT = 'https://api.zenmanage.com';

    public function __construct(
        private readonly string $environmentToken,
        private readonly int $cacheTtl = self::DEFAULT_CACHE_TTL,
        private readonly string $cacheBackend = self::DEFAULT_CACHE_BACKEND,
        private readonly ?string $cacheDirectory = null,
        private readonly bool $enableUsageReporting = true,
        private readonly string $apiEndpoint = self::DEFAULT_API_ENDPOINT,
    ) {
        $this->validate();
    }

    public function getEnvironmentToken(): string
    {
        return $this->environmentToken;
    }

    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    public function getCacheBackend(): string
    {
        return $this->cacheBackend;
    }

    public function getCacheDirectory(): ?string
    {
        return $this->cacheDirectory;
    }

    public function isUsageReportingEnabled(): bool
    {
        return $this->enableUsageReporting;
    }

    public function getApiEndpoint(): string
    {
        return $this->apiEndpoint;
    }

    private function validate(): void
    {
        if (empty($this->environmentToken)) {
            throw new ConfigurationException('Environment token is required');
        }

        if ($this->cacheTtl < 0) {
            throw new ConfigurationException('Cache TTL must be non-negative');
        }

        if (!in_array($this->cacheBackend, ['memory', 'filesystem', 'null'], true)) {
            throw new ConfigurationException('Invalid cache backend: ' . $this->cacheBackend);
        }

        if ($this->cacheBackend === 'filesystem' && empty($this->cacheDirectory)) {
            throw new ConfigurationException('Cache directory is required for filesystem cache backend');
        }
    }
}
