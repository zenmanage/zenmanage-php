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
use Zenmanage\Flags\DefaultsCollection;
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
        /** @var \Zenmanage\Api\ApiClientInterface $apiClient */
        $apiClient = $this->apiClient;
        /** @var \Zenmanage\Cache\CacheInterface $cache */
        $cache = $this->cache;
        /** @var \Zenmanage\Rules\RuleEngineInterface $ruleEngine */
        $ruleEngine = $this->ruleEngine;

        return new FlagManager(
            apiClient: $apiClient,
            cache: $cache,
            ruleEngine: $ruleEngine,
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

    public function testAllFallsBackToDefaultsWhenRuleLoadingFails(): void
    {
        $this->expectOnce($this->cache, 'get')->andReturn(null);
        $this->expectOnce($this->apiClient, 'getRules')->andThrow(new \RuntimeException('API unavailable'));
        $this->expectNever($this->cache, 'set');

        $defaults = DefaultsCollection::fromArray([
            'flag-a' => true,
            'flag-b' => 'fallback',
        ]);

        $manager = $this->createManager()->withDefaults($defaults);

        $flags = $manager->all();

        $this->assertCount(2, $flags);

        $byKey = [];
        foreach ($flags as $flag) {
            $byKey[$flag->getKey()] = $flag;
        }

        $this->assertTrue($byKey['flag-a']->asBool());
        $this->assertSame('fallback', $byKey['flag-b']->asString());
    }

    public function testAllMergesDefaultsForKeysMissingFromLoadedFlags(): void
    {
        $this->expectOnce($this->cache, 'get')->andReturn(null);
        $this->expectOnce($this->apiClient, 'getRules')->andReturn($this->fixtureResponse());
        $this->expectOnce($this->cache, 'set');

        $this->expectOnce($this->ruleEngine, 'evaluate')->andReturn(['boolean' => true]);

        $defaults = DefaultsCollection::fromArray([
            'test-feature' => false,
            'other-flag' => 'default-value',
        ]);

        $manager = $this->createManager()->withDefaults($defaults);

        $flags = $manager->all();

        $this->assertCount(2, $flags);

        $byKey = [];
        foreach ($flags as $flag) {
            $byKey[$flag->getKey()] = $flag;
        }

        // The loaded flag's evaluated value takes priority over its default entry
        $this->assertTrue($byKey['test-feature']->asBool());
        $this->assertSame('default-value', $byKey['other-flag']->asString());
    }

    public function testSingleUsesApiWhenCacheMissingAndReportsUsage(): void
    {
        $this->expectOnce($this->cache, 'get')->andReturn(null);
        $this->expectOnce($this->apiClient, 'getRules')->andReturn($this->fixtureResponse());
        $this->expectOnce($this->cache, 'set');

        $context = Context::single('user', 'user-123');
        $this->expectOnce($this->apiClient, 'reportUsage')->with('test-feature', $context, null);

        $this->expectOnce($this->ruleEngine, 'evaluate')->andReturn(['boolean' => true]);

        $manager = $this->createManager();
        $flag = $manager->withContext($context)->single('test-feature');

        $this->assertTrue($flag->asBool());
        $this->assertSame('test-feature', $flag->getKey());
    }

    public function testSingleReportsNullDefaultWhenFlagIsFoundAndNoDefaultProvided(): void
    {
        $this->expectOnce($this->cache, 'get')->andReturn(null);
        $this->expectOnce($this->apiClient, 'getRules')->andReturn($this->fixtureResponse());
        $this->expectOnce($this->cache, 'set');

        $this->expectOnce($this->apiClient, 'reportUsage')->with('test-feature', null, null);

        $this->expectOnce($this->ruleEngine, 'evaluate')->andReturn(['boolean' => true]);

        $manager = $this->createManager();
        $flag = $manager->single('test-feature');

        $this->assertTrue($flag->asBool());
    }

    public function testSingleReportsInlineDefaultWhenFlagIsFound(): void
    {
        $this->expectOnce($this->cache, 'get')->andReturn(null);
        $this->expectOnce($this->apiClient, 'getRules')->andReturn($this->fixtureResponse());
        $this->expectOnce($this->cache, 'set');

        $this->expectOnce($this->apiClient, 'reportUsage')->with('test-feature', null, false);

        $this->expectOnce($this->ruleEngine, 'evaluate')->andReturn(['boolean' => true]);

        $manager = $this->createManager();
        $flag = $manager->single('test-feature', false);

        $this->assertTrue($flag->asBool());
    }

    public function testSingleReportsCollectionDefaultWhenFlagIsFound(): void
    {
        $this->expectOnce($this->cache, 'get')->andReturn(null);
        $this->expectOnce($this->apiClient, 'getRules')->andReturn($this->fixtureResponse());
        $this->expectOnce($this->cache, 'set');

        $this->expectOnce($this->apiClient, 'reportUsage')->with('test-feature', null, false);

        $this->expectOnce($this->ruleEngine, 'evaluate')->andReturn(['boolean' => true]);

        $defaults = DefaultsCollection::fromArray(['test-feature' => false]);

        $manager = $this->createManager()->withDefaults($defaults);
        $flag = $manager->single('test-feature');

        $this->assertTrue($flag->asBool());
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

    public function testSingleFallsBackToCollectionDefaultWhenRuleLoadingFails(): void
    {
        $this->expectOnce($this->cache, 'get')->andReturn(null);
        $this->expectOnce($this->apiClient, 'getRules')->andThrow(new \RuntimeException('API unavailable'));
        $this->expectNever($this->cache, 'set');

        $this->expectOnce($this->apiClient, 'reportUsage')->with('test-feature', null, 'collection-default');

        $defaults = DefaultsCollection::fromArray(['test-feature' => 'collection-default']);

        $manager = $this->createManager()->withDefaults($defaults);
        $flag = $manager->single('test-feature');

        $this->assertSame('collection-default', $flag->asString());
    }

    public function testSingleThrowsWhenRuleLoadingFailsAndNoDefaults(): void
    {
        $this->expectOnce($this->cache, 'get')->andReturn(null);
        $this->expectOnce($this->apiClient, 'getRules')->andThrow(new \RuntimeException('API unavailable'));
        $this->expectNever($this->cache, 'set');
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

    public function testReportUsageCanBeCalledDirectlyWithoutContext(): void
    {
        $this->expectNever($this->cache, 'get');
        $this->apiClient->shouldNotReceive('getRules');
        $this->expectOnce($this->apiClient, 'reportUsage')->with('some-flag', null, null);

        $this->createManager()->reportUsage('some-flag', null);

        // Assertion is enforced by Mockery expectation above (verified in tearDown)
        $this->addToAssertionCount(1);
    }
}
