<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Flags;

use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Zenmanage\Api\ApiClientInterface;
use Zenmanage\Api\Response\RulesResponse;
use Zenmanage\Cache\CacheInterface;
use Zenmanage\Exception\EvaluationException;
use Zenmanage\Flags\Context\Context;
use Zenmanage\Flags\FlagManager;
use Zenmanage\Rules\RuleEngineInterface;

final class FlagManagerTest extends TestCase
{
    private \Mockery\MockInterface $apiClient;

    private \Mockery\MockInterface $cache;

    private \Mockery\MockInterface $ruleEngine;

    protected function setUp(): void
    {
        $this->apiClient = Mockery::mock(ApiClientInterface::class);
        $this->cache = Mockery::mock(CacheInterface::class);
        $this->ruleEngine = Mockery::mock(RuleEngineInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(): array
    {
        $contents = file_get_contents(__DIR__ . '/../../Fixtures/rules.json') ?: '';

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($contents, true) ?? [];

        return $decoded;
    }

    private function fixtureResponse(): RulesResponse
    {
        return RulesResponse::fromArray($this->loadFixture());
    }

    /**
     * @return \Mockery\Expectation
     */
    private function expectOnce(Mockery\MockInterface $mock, string $method)
    {
        /** @var \Mockery\Expectation $expectation */
        $expectation = $mock->shouldReceive($method);

        return $expectation->once();
    }

    /**
     * @return \Mockery\Expectation
     */
    private function expectNever(Mockery\MockInterface $mock, string $method)
    {
        /** @var \Mockery\Expectation $expectation */
        $expectation = $mock->shouldReceive($method);

        return $expectation->never();
    }

    private function createManager(): FlagManager
    {
        return new FlagManager(
            apiClient: $this->apiClient,
            cache: $this->cache,
            ruleEngine: $this->ruleEngine,
            cacheTtl: 3600,
            logger: new NullLogger(),
        );
    }

    public function testAllUsesCachedRules(): void
    {
        $data = $this->loadFixture();

        $this->expectOnce($this->cache, 'get')->andReturn(json_encode($data));
        $this->apiClient->shouldNotReceive('getRules');
        $this->expectNever($this->cache, 'set');

        $this->expectOnce($this->ruleEngine, 'evaluate')->andReturn(['boolean' => false]);

        $manager = $this->createManager();
        $flags = $manager->all();

        $this->assertCount(1, $flags);
        $this->assertFalse($flags[0]->asBool());
    }

    public function testSingleUsesApiWhenCacheMissingAndReportsUsage(): void
    {
        $this->expectOnce($this->cache, 'get')->andReturn(null);
        $this->expectOnce($this->apiClient, 'getRules')->andReturn($this->fixtureResponse());
        $this->expectOnce($this->cache, 'set');

        $context = Context::single('user', 'user-123');
        $this->expectOnce($this->apiClient, 'reportUsage')->with('test-feature', $context);

        $this->expectOnce($this->ruleEngine, 'evaluate')->andReturn(['boolean' => true]);

        $manager = $this->createManager();
        $flag = $manager->withContext($context)->single('test-feature');

        $this->assertTrue($flag->asBool());
        $this->assertSame('test-feature', $flag->getKey());
    }

    public function testSingleThrowsWhenFlagMissingAndNoDefaults(): void
    {
        $this->expectOnce($this->cache, 'get')->andReturn(null);
        $this->expectOnce($this->apiClient, 'getRules')->andReturn(new RulesResponse('v1', []));
        $this->expectOnce($this->cache, 'set');
        $this->expectNever($this->apiClient, 'reportUsage');

        $manager = $this->createManager();

        $this->expectException(EvaluationException::class);
        $manager->single('missing-flag');
    }

    public function testRefreshRulesReloadsFromApi(): void
    {
        $this->expectNever($this->cache, 'get');
        $this->expectOnce($this->apiClient, 'getRules')->andReturn($this->fixtureResponse());
        $this->expectOnce($this->cache, 'set');

        $this->expectOnce($this->ruleEngine, 'evaluate')->andReturn(['boolean' => false]);

        $manager = $this->createManager();
        $manager->refreshRules();

        $flags = $manager->all();

        $this->assertCount(1, $flags);
        $this->assertFalse($flags[0]->asBool());
    }

    public function testInvalidCachedJsonFallsBackToApi(): void
    {
        $this->expectOnce($this->cache, 'get')->andReturn('{invalid json');
        $this->expectOnce($this->apiClient, 'getRules')->andReturn($this->fixtureResponse());
        $this->expectOnce($this->cache, 'set');

        $this->expectOnce($this->ruleEngine, 'evaluate')->andReturn(['boolean' => true]);

        $manager = $this->createManager();
        $flags = $manager->all();

        $this->assertCount(1, $flags);
        $this->assertTrue($flags[0]->asBool());
    }
}
