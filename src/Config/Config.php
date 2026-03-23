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
    private const SERVER_KEY_PREFIX = 'srv_';
    private const CLIENT_KEY_PREFIX = 'cli_';
    private const MOBILE_KEY_PREFIX = 'mob_';

    public function __construct(
        private readonly string $environmentToken,
        private readonly int $cacheTtl = self::DEFAULT_CACHE_TTL,
        private readonly string $cacheBackend = self::DEFAULT_CACHE_BACKEND,
        private readonly ?string $cacheDirectory = null,
        private readonly bool $enableUsageReporting = true,
        private readonly string $apiEndpoint = self::DEFAULT_API_ENDPOINT,
        private readonly ?string $sdkVersion = null,
        private readonly ?string $clientAgent = null,
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

    public function getSdkVersion(): ?string
    {
        return $this->sdkVersion;
    }

    public function getClientAgent(): ?string
    {
        return $this->clientAgent;
    }

    private function validate(): void
    {
        if (empty($this->environmentToken)) {
            throw new ConfigurationException('Environment token is required');
        }

        if (str_starts_with($this->environmentToken, self::CLIENT_KEY_PREFIX)) {
            throw new ConfigurationException('Unsupported key type for PHP SDK: client key provided (cli_). Use a server key (srv_).');
        }

        if (str_starts_with($this->environmentToken, self::MOBILE_KEY_PREFIX)) {
            throw new ConfigurationException('Unsupported key type for PHP SDK: mobile key provided (mob_). Use a server key (srv_).');
        }

        if (!str_starts_with($this->environmentToken, self::SERVER_KEY_PREFIX)) {
            throw new ConfigurationException('Invalid environment token for PHP SDK. Expected a case-sensitive server key prefixed with srv_.');
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

        if ($this->sdkVersion !== null && $this->sdkVersion === '') {
            throw new ConfigurationException('SDK version cannot be an empty string');
        }

        if ($this->clientAgent !== null && $this->clientAgent === '') {
            throw new ConfigurationException('Client agent cannot be an empty string');
        }
    }
}
