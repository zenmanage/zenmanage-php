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

    private function loadFixture(): array
    {
        $contents = file_get_contents(__DIR__ . '/../../Fixtures/rules.json') ?: '';

        return json_decode($contents, true);
    }

    private function fixtureResponse(): RulesResponse
    {
        return RulesResponse::fromArray($this->loadFixture());
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

        $this->cache->shouldReceive('get')->once()->andReturn(json_encode($data));
        $this->apiClient->shouldNotReceive('getRules');
        $this->cache->shouldReceive('set')->never();

        $this->ruleEngine->shouldReceive('evaluate')->once()->andReturn(['boolean' => false]);

        $manager = $this->createManager();
        $flags = $manager->all();

        $this->assertCount(1, $flags);
        $this->assertFalse($flags[0]->asBool());
    }

    public function testSingleUsesApiWhenCacheMissingAndReportsUsage(): void
    {
        $this->cache->shouldReceive('get')->once()->andReturn(null);
        $this->apiClient->shouldReceive('getRules')->once()->andReturn($this->fixtureResponse());
        $this->cache->shouldReceive('set')->once();

        $context = Context::single('user', 'user-123');
        $this->apiClient->shouldReceive('reportUsage')->once()->with('test-feature', $context);

        $this->ruleEngine->shouldReceive('evaluate')->once()->andReturn(['boolean' => true]);

        $manager = $this->createManager();
        $flag = $manager->withContext($context)->single('test-feature');

        $this->assertTrue($flag->asBool());
        $this->assertSame('test-feature', $flag->getKey());
    }

    public function testSingleThrowsWhenFlagMissingAndNoDefaults(): void
    {
        $this->cache->shouldReceive('get')->once()->andReturn(null);
        $this->apiClient->shouldReceive('getRules')->once()->andReturn(new RulesResponse('v1', []));
        $this->cache->shouldReceive('set')->once();
        $this->apiClient->shouldReceive('reportUsage')->never();

        $manager = $this->createManager();

        $this->expectException(EvaluationException::class);
        $manager->single('missing-flag');
    }

    public function testRefreshRulesReloadsFromApi(): void
    {
        $this->cache->shouldReceive('get')->never();
        $this->apiClient->shouldReceive('getRules')->once()->andReturn($this->fixtureResponse());
        $this->cache->shouldReceive('set')->once();

        $this->ruleEngine->shouldReceive('evaluate')->once()->andReturn(['boolean' => false]);

        $manager = $this->createManager();
        $manager->refreshRules();

        $flags = $manager->all();

        $this->assertCount(1, $flags);
        $this->assertFalse($flags[0]->asBool());
    }

    public function testInvalidCachedJsonFallsBackToApi(): void
    {
        $this->cache->shouldReceive('get')->once()->andReturn('{invalid json');
        $this->apiClient->shouldReceive('getRules')->once()->andReturn($this->fixtureResponse());
        $this->cache->shouldReceive('set')->once();

        $this->ruleEngine->shouldReceive('evaluate')->once()->andReturn(['boolean' => true]);

        $manager = $this->createManager();
        $flags = $manager->all();

        $this->assertCount(1, $flags);
        $this->assertTrue($flags[0]->asBool());
    }
}
