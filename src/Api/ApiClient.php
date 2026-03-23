<?php

declare(strict_types=1);

namespace Zenmanage\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Zenmanage\Api\Response\RulesResponse;
use Zenmanage\Exception\FetchRulesException;
use Zenmanage\Exception\InvalidRulesException;

/**
 * API client for communicating with the Zenmanage service.
 */
final class ApiClient implements ApiClientInterface
{
    private const DEFAULT_API_ENDPOINT = 'https://api.zenmanage.com';
    private const RULES_PATH = '/v1/flag-json';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 100;
    private const DEFAULT_SDK_VERSION = '4.0.3';
    private const PHP_CLIENT_AGENT = 'zenmanage-php';
    private const LARAVEL_CLIENT_AGENT = 'zenmanage-laravel';

    private readonly Client $httpClient;
    private readonly string $clientAgent;
    private readonly string $sdkVersion;

    public function __construct(
        private readonly string $environmentToken,
        private readonly string $apiEndpoint = self::DEFAULT_API_ENDPOINT,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $enableUsageReporting = true,
        ?string $sdkVersion = null,
        ?Client $httpClient = null,
        ?string $clientAgent = null,
    ) {
        $this->clientAgent = $this->resolveClientAgent($clientAgent);
        $this->sdkVersion = $this->resolveSdkVersion($sdkVersion);

        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->apiEndpoint,
            'timeout' => 10.0,
            'headers' => [
                'Accept' => 'application/json',
                'X-API-Key' => $this->environmentToken,
                'X-ZEN-CLIENT-AGENT' => $this->clientAgent . '/' . $this->sdkVersion,
            ],
        ]);
    }

    private function resolveClientAgent(?string $clientAgent): string
    {
        if ($clientAgent !== null && $clientAgent !== '') {
            return $clientAgent;
        }

        if (defined('LARAVEL_START') || class_exists('Illuminate\\Foundation\\Application')) {
            return self::LARAVEL_CLIENT_AGENT;
        }

        return self::PHP_CLIENT_AGENT;
    }

    private function resolveSdkVersion(?string $sdkVersion): string
    {
        if ($sdkVersion !== null && $sdkVersion !== '') {
            return $sdkVersion;
        }

        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                if (\Composer\InstalledVersions::isInstalled('zenmanage/zenmanage-php')) {
                    $version = \Composer\InstalledVersions::getPrettyVersion('zenmanage/zenmanage-php');

                    if (is_string($version) && $version !== '') {
                        return $version;
                    }
                }
            } catch (\Throwable) {
                // Fallback to default when composer metadata is unavailable.
            }
        }

        return self::DEFAULT_SDK_VERSION;
    }

    public function getRules(): RulesResponse
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            try {
                return $this->fetchRulesWithRetry($attempt);
            } catch (InvalidRulesException $e) {
                throw $e;
            } catch (GuzzleException $e) {
                $lastException = $e;
                $this->handleRetryDelay($attempt, $e);
            }
        }

        return $this->throwFetchRulesException($lastException);
    }

    public function reportUsage(string $flagKey, ?\Zenmanage\Flags\Context\Context $context = null): void
    {
        if (!$this->enableUsageReporting) {
            $this->logger->debug('Usage reporting disabled, skipping API call', [
                'key' => $flagKey,
            ]);

            return;
        }

        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $this->logger->debug('Reporting flag usage', [
                    'key' => $flagKey,
                    'attempt' => $attempt + 1,
                ]);

                // Build headers with optional context
                $headers = [];
                if ($context !== null) {
                    $headers['X-ZENMANAGE-CONTEXT'] = json_encode($context->jsonSerialize());
                }

                $this->httpClient->post("/v1/flags/{$flagKey}/usage", [
                    'headers' => $headers,
                ]);

                $this->logger->info('Successfully reported flag usage', ['key' => $flagKey]);

                return;
            } catch (GuzzleException $e) {
                $attempt++;

                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAY_MS * (2 ** ($attempt - 1)); // Exponential backoff
                    $this->logger->debug('Failed to report usage, retrying', [
                        'key' => $flagKey,
                        'attempt' => $attempt,
                        'delay_ms' => $delay,
                        'error' => $e->getMessage(),
                    ]);

                    usleep($delay * 1000);
                } else {
                    // Log but don't throw - usage reporting shouldn't break the application
                    $this->logger->warning('Failed to report flag usage after all retries', [
                        'key' => $flagKey,
                        'attempts' => self::MAX_RETRIES,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Fetch rules from API and CDN with logging.
     */
    private function fetchRulesWithRetry(int $attempt): RulesResponse
    {
        $this->logger->debug('Fetching rules metadata from API', [
            'endpoint' => $this->apiEndpoint . self::RULES_PATH,
            'attempt' => $attempt + 1,
        ]);

        $rulesUrl = $this->getCdnRulesUrl();

        $this->logger->debug('Fetching rules from CDN', [
            'url' => $rulesUrl,
        ]);

        $rulesBody = (string) $this->httpClient->get($rulesUrl)->getBody();

        $this->logger->info('Successfully fetched rules from CDN', [
            'size' => strlen($rulesBody),
        ]);

        $data = json_decode($rulesBody, true);

        if (!is_array($data)) {
            throw new InvalidRulesException('Rules JSON is not valid');
        }

        return RulesResponse::fromArray($data);
    }

    /**
     * Get the CDN URL for rules from the API metadata endpoint.
     */
    private function getCdnRulesUrl(): string
    {
        $response = $this->httpClient->get(self::RULES_PATH);
        $body = (string) $response->getBody();

        $metadata = json_decode($body, true);

        if (!is_array($metadata)) {
            throw new InvalidRulesException('API response is not valid JSON');
        }

        if (!isset($metadata['data']['cdn'], $metadata['data']['path'])) {
            throw new InvalidRulesException('API response missing cdn or path fields');
        }

        $cdn = $metadata['data']['cdn'];
        $path = $metadata['data']['path'];

        if (!is_string($cdn) || !is_string($path)) {
            throw new InvalidRulesException('cdn or path fields are not strings');
        }

        return $cdn . $path;
    }

    /**
     * Handle retry delay with exponential backoff.
     */
    private function handleRetryDelay(int $attempt, GuzzleException $e): void
    {
        if ($attempt + 1 < self::MAX_RETRIES) {
            $delay = self::RETRY_DELAY_MS * (2 ** $attempt); // Exponential backoff
            $this->logger->warning('Failed to fetch rules, retrying', [
                'attempt' => $attempt + 1,
                'delay_ms' => $delay,
                'error' => $e->getMessage(),
            ]);

            usleep($delay * 1000);
        }
    }

    /**
     * @throws FetchRulesException
     */
    private function throwFetchRulesException(?GuzzleException $lastException): never
    {
        $this->logger->error('Failed to fetch rules after all retries', [
            'attempts' => self::MAX_RETRIES,
            'error' => $lastException?->getMessage(),
        ]);

        throw new FetchRulesException(
            'Failed to fetch rules from API after ' . self::MAX_RETRIES . ' attempts',
            0,
            $lastException,
        );
    }
}
