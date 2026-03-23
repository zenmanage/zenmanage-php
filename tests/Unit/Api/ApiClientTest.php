<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Zenmanage\Api\ApiClient;
use Zenmanage\Exception\FetchRulesException;
use Zenmanage\Exception\InvalidRulesException;
use Zenmanage\Flags\Context\Context;

final class ApiClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function makeClient(Mockery\MockInterface $httpClient): ApiClient
    {
        return new ApiClient(
            environmentToken: 'env-token',
            apiEndpoint: 'https://api.example.com',
            logger: new NullLogger(),
            httpClient: $httpClient,
        );
    }

    private function rulesFixture(): string
    {
        $contents = file_get_contents(__DIR__ . '/../../Fixtures/rules.json');

        return is_string($contents) ? $contents : '';
    }

    private function guzzleException(string $message): GuzzleException
    {
        return new class ($message) extends \Exception implements GuzzleException {
            public function __construct(string $message)
            {
                parent::__construct($message);
            }
        };
    }

    public function testGetRulesReturnsParsedResponse(): void
    {
        $httpClient = Mockery::mock(Client::class);

        $metadata = new Response(200, [], json_encode([
            'data' => [
                'cdn' => 'https://cdn.example.com',
                'path' => '/rules.json',
            ],
        ]) ?: '');

        $rules = new Response(200, [], $this->rulesFixture());

        /** @var \Mockery\Expectation $getMetadata */
        $getMetadata = $httpClient->shouldReceive('get');
        $getMetadata->once()->with('/v1/flag-json')->andReturn($metadata);

        /** @var \Mockery\Expectation $getRules */
        $getRules = $httpClient->shouldReceive('get');
        $getRules->once()->with('https://cdn.example.com/rules.json')->andReturn($rules);

        $client = $this->makeClient($httpClient);
        $response = $client->getRules();

        $this->assertSame('2026-01-15', $response->getVersion());
        $this->assertNotNull($response->getFlagByKey('test-feature'));
    }

    public function testGetRulesThrowsWhenMetadataInvalid(): void
    {
        $httpClient = Mockery::mock(Client::class);
        /** @var \Mockery\Expectation $get */
        $get = $httpClient->shouldReceive('get');
        $get->once()->with('/v1/flag-json')->andReturn(new Response(200, [], 'not-json'));

        $client = $this->makeClient($httpClient);

        $this->expectException(InvalidRulesException::class);
        $client->getRules();
    }

    public function testGetRulesRetriesAndThrowsWhenAllAttemptsFail(): void
    {
        $httpClient = Mockery::mock(Client::class);
        $exception = $this->guzzleException('network down');

        /** @var \Mockery\Expectation $get */
        $get = $httpClient->shouldReceive('get');
        $get->times(3)->with('/v1/flag-json')->andThrow($exception);

        $client = $this->makeClient($httpClient);

        $this->expectException(FetchRulesException::class);
        $client->getRules();
    }

    public function testReportUsageSendsContextHeader(): void
    {
        $httpClient = Mockery::mock(Client::class);
        /** @var \Mockery\Expectation $post */
        $post = $httpClient->shouldReceive('post');
        $post->once()->with(
            '/v1/flags/example/usage',
            Mockery::on(function (array $args): bool {
                $encoded = $args['headers']['X-ZENMANAGE-CONTEXT'] ?? '';
                $decoded = json_decode((string) $encoded, true);

                return is_array($decoded)
                    && isset($decoded['identifier'])
                    && $decoded['identifier'] === 'abc-123';
            })
        )->andReturn(new Response(200));

        $client = $this->makeClient($httpClient);
        $client->reportUsage('example', Context::single('user', 'abc-123'));

        $this->assertTrue(true); // avoid risky test warning
    }

    public function testReportUsageSwallowsErrorsAfterRetries(): void
    {
        $httpClient = Mockery::mock(Client::class);
        $exception = $this->guzzleException('network down');

        /** @var \Mockery\Expectation $post */
        $post = $httpClient->shouldReceive('post');
        $post->times(3)->andThrow($exception);

        $client = $this->makeClient($httpClient);

        $client->reportUsage('flag-key');
        $this->assertTrue(true); // ensure no exception bubbles
    }

    public function testClientAgentCanBeOverridden(): void
    {
        $client = new ApiClient(
            environmentToken: 'env-token',
            clientAgent: 'zenmanage-laravel',
        );

        $readClientAgent = \Closure::bind(
            static fn (ApiClient $apiClient): string => $apiClient->clientAgent,
            null,
            ApiClient::class,
        );

        $this->assertIsCallable($readClientAgent);
        $this->assertSame('zenmanage-laravel', $readClientAgent($client));
    }

    public function testSdkVersionCanBeOverridden(): void
    {
        $client = new ApiClient(
            environmentToken: 'env-token',
            sdkVersion: '9.9.9',
        );

        $readSdkVersion = \Closure::bind(
            static fn (ApiClient $apiClient): string => $apiClient->sdkVersion,
            null,
            ApiClient::class,
        );

        $this->assertIsCallable($readSdkVersion);
        $this->assertSame('9.9.9', $readSdkVersion($client));
    }

    public function testClientHeaderUsesOverrides(): void
    {
        $client = new ApiClient(
            environmentToken: 'env-token',
            clientAgent: 'zenmanage-laravel',
            sdkVersion: '9.9.9',
        );

        $readHttpClient = \Closure::bind(
            static fn (ApiClient $apiClient): Client => $apiClient->httpClient,
            null,
            ApiClient::class,
        );

        $this->assertIsCallable($readHttpClient);
        $httpClient = $readHttpClient($client);
        $headers = $httpClient->getConfig('headers');

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('X-ZEN-CLIENT-AGENT', $headers);
        $this->assertSame('zenmanage-laravel/9.9.9', $headers['X-ZEN-CLIENT-AGENT']);
    }

    public function testDefaultClientAgentIsRecognized(): void
    {
        $client = new ApiClient(environmentToken: 'env-token');

        $readClientAgent = \Closure::bind(
            static fn (ApiClient $apiClient): string => $apiClient->clientAgent,
            null,
            ApiClient::class,
        );

        $this->assertIsCallable($readClientAgent);
        $clientAgent = $readClientAgent($client);

        $this->assertContains($clientAgent, ['zenmanage-php', 'zenmanage-laravel']);
    }
}
