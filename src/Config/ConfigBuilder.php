<?php

declare(strict_types=1);

namespace Zenmanage\Config;

/**
 * Fluent builder for creating Config objects.
 */
final class ConfigBuilder
{
    private ?string $environmentToken = null;
    private int $cacheTtl = 3600;
    private string $cacheBackend = 'memory';
    private ?string $cacheDirectory = null;
    private bool $enableUsageReporting = false;
    private string $apiEndpoint = 'https://api.zenmanage.com';

    private function __construct()
    {
    }

    /**
     * Create a new ConfigBuilder instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Create a ConfigBuilder with values from environment variables.
     */
    public static function fromEnvironment(): self
    {
        $builder = new self();

        $token = getenv('ZENMANAGE_ENVIRONMENT_TOKEN') ?: null;

        if ($token !== null) {
            $builder = $builder->withEnvironmentToken($token);
        }

        $cacheTtl = getenv('ZENMANAGE_CACHE_TTL');

        if ($cacheTtl !== false && is_numeric($cacheTtl)) {
            $builder = $builder->withCacheTtl((int) $cacheTtl);
        }

        $cacheBackend = getenv('ZENMANAGE_CACHE_BACKEND');

        if ($cacheBackend !== false) {
            $builder = $builder->withCacheBackend($cacheBackend);
        }

        $cacheDirectory = getenv('ZENMANAGE_CACHE_DIR') ?: null;

        if ($cacheDirectory !== null) {
            $builder = $builder->withCacheDirectory($cacheDirectory);
        }

        $enableUsageReporting = getenv('ZENMANAGE_ENABLE_USAGE_REPORTING');

        if ($enableUsageReporting !== false) {
            $enabled = filter_var($enableUsageReporting, FILTER_VALIDATE_BOOLEAN);

            if ($enabled) {
                $builder = $builder->enableUsageReporting();
            }
        }

        $apiEndpoint = getenv('ZENMANAGE_API_ENDPOINT') ?: null;

        if ($apiEndpoint !== null) {
            $builder = $builder->withApiEndpoint($apiEndpoint);
        }

        return $builder;
    }

    public function withEnvironmentToken(string $token): self
    {
        $this->environmentToken = $token;

        return $this;
    }

    public function withCacheTtl(int $ttl): self
    {
        $this->cacheTtl = $ttl;

        return $this;
    }

    public function withCacheBackend(string $backend): self
    {
        $this->cacheBackend = $backend;

        return $this;
    }

    public function withCacheDirectory(string $directory): self
    {
        $this->cacheDirectory = $directory;

        return $this;
    }

    public function enableUsageReporting(): self
    {
        $this->enableUsageReporting = true;

        return $this;
    }

    public function disableUsageReporting(): self
    {
        $this->enableUsageReporting = false;

        return $this;
    }

    public function withApiEndpoint(string $endpoint): self
    {
        $this->apiEndpoint = $endpoint;

        return $this;
    }

    /**
     * Build the Config object.
     */
    public function build(): Config
    {
        return new Config(
            environmentToken: $this->environmentToken ?? '',
            cacheTtl: $this->cacheTtl,
            cacheBackend: $this->cacheBackend,
            cacheDirectory: $this->cacheDirectory,
            enableUsageReporting: $this->enableUsageReporting,
            apiEndpoint: $this->apiEndpoint,
        );
    }
}
