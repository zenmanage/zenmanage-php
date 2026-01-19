<?php

declare(strict_types=1);

namespace Zenmanage;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Zenmanage\Api\ApiClient;
use Zenmanage\Cache\CacheInterface;
use Zenmanage\Cache\FileSystemCache;
use Zenmanage\Cache\InMemoryCache;
use Zenmanage\Cache\NullCache;
use Zenmanage\Config\Config;
use Zenmanage\Exception\ConfigurationException;
use Zenmanage\Flags\FlagManager;
use Zenmanage\Flags\FlagManagerInterface;
use Zenmanage\Rules\RuleEngine;

/**
 * Main entry point for the Zenmanage SDK.
 */
final class Zenmanage
{
    private readonly FlagManagerInterface $flagManager;
    private ?\Zenmanage\Flags\Context\Context $context = null;

    public function __construct(
        Config $config,
        ?LoggerInterface $logger = null,
    ) {
        $logger = $logger ?? new NullLogger();

        // Create cache instance
        $cache = $this->createCache($config);

        // Create API client
        $apiClient = new ApiClient(
            environmentToken: $config->getEnvironmentToken(),
            apiEndpoint: $config->getApiEndpoint(),
            logger: $logger,
        );

        // Create rule engine
        $ruleEngine = new RuleEngine();

        // Create flag manager
        $this->flagManager = new FlagManager(
            apiClient: $apiClient,
            cache: $cache,
            ruleEngine: $ruleEngine,
            cacheTtl: $config->getCacheTtl(),
            logger: $logger,
        );
    }

    /**
     * Set the context for flag evaluation.
     * This should be called before flags() to ensure context is sent to the API.
     *
     * @param \Zenmanage\Flags\Context\Context $context
     * @return self
     */
    public function withContext(\Zenmanage\Flags\Context\Context $context): self
    {
        $clone = clone $this;
        $clone->context = $context;

        return $clone;
    }

    /**
     * Get the flag manager instance for evaluation.
     * Use withContext() before this method to send context to the API.
     */
    public function flags(): FlagManagerInterface
    {
        if ($this->context !== null) {
            return $this->flagManager->withContext($this->context);
        }

        return $this->flagManager;
    }

    /**
     * Create a cache instance based on configuration.
     */
    private function createCache(Config $config): CacheInterface
    {
        return match ($config->getCacheBackend()) {
            'filesystem' => new FileSystemCache(
                $config->getCacheDirectory() ?? throw new ConfigurationException('Cache directory required for filesystem cache'),
            ),
            'memory' => new InMemoryCache(),
            'null' => new NullCache(),
            default => throw new ConfigurationException('Invalid cache backend: ' . $config->getCacheBackend()),
        };
    }
}
