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
    private const SDK_VERSION = '2.0.0';
    private const CLIENT_AGENT = 'zenmanage-php';

    private readonly Client $httpClient;

    public function __construct(
        private readonly string $environmentToken,
        private readonly string $apiEndpoint = self::DEFAULT_API_ENDPOINT,
        private readonly LoggerInterface $logger = new NullLogger(),
        ?Client $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->apiEndpoint,
            'timeout' => 10.0,
            'headers' => [
                'Accept' => 'application/json',
                'X-API-Key' => $this->environmentToken,
                'X-ZEN-CLIENT-AGENT' => self::CLIENT_AGENT . '/' . self::SDK_VERSION,
            ],
        ]);
    }

    public function getRules(\Zenmanage\Flags\Context\Context $context = null): RulesResponse
    {
        $attempt = 0;
        /** @var GuzzleException|null $lastException */
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $this->logger->debug('Fetching rules metadata from API', [
                    'endpoint' => $this->apiEndpoint . self::RULES_PATH,
                    'attempt' => $attempt + 1,
                ]);

                // Build headers with optional context
                $headers = [];
                if ($context !== null) {
                    $headers['X-ZENMANAGE-CONTEXT'] = json_encode($context->jsonSerialize());
                }

                // Step 1: Get the CDN path from flag-json endpoint
                $response = $this->httpClient->get(self::RULES_PATH, [
                    'headers' => $headers,
                ]);
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

                $rulesUrl = $cdn . $path;

                $this->logger->debug('Fetching rules from CDN', [
                    'url' => $rulesUrl,
                ]);

                // Step 2: Fetch the actual rules from the CDN
                $rulesResponse = $this->httpClient->get($rulesUrl);
                $rulesBody = (string) $rulesResponse->getBody();

                $this->logger->info('Successfully fetched rules from CDN', [
                    'size' => strlen($rulesBody),
                ]);

                $data = json_decode($rulesBody, true);

                if (!is_array($data)) {
                    throw new InvalidRulesException('Rules JSON is not valid');
                }

                return RulesResponse::fromArray($data);
            } catch (GuzzleException $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAY_MS * (2 ** ($attempt - 1)); // Exponential backoff
                    $this->logger->warning('Failed to fetch rules, retrying', [
                        'attempt' => $attempt,
                        'delay_ms' => $delay,
                        'error' => $e->getMessage(),
                    ]);

                    usleep($delay * 1000);
                }
            } catch (InvalidRulesException $e) {
                throw $e;
            }
        }

        assert($lastException instanceof GuzzleException);

        $this->logger->error('Failed to fetch rules after all retries', [
            'attempts' => self::MAX_RETRIES,
            'error' => $lastException->getMessage(),
        ]);

        throw new FetchRulesException(
            'Failed to fetch rules from API after ' . self::MAX_RETRIES . ' attempts',
            0,
            $lastException,
        );
    }

    public function reportUsage(string $flagKey): void
    {
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $this->logger->debug('Reporting flag usage', [
                    'key' => $flagKey,
                    'attempt' => $attempt + 1,
                ]);

                $this->httpClient->post("/v1/flags/{$flagKey}/usage");

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
}
