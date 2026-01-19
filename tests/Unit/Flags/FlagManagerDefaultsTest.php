<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Flags;

use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Zenmanage\Api\ApiClientInterface;
use Zenmanage\Api\Response\RulesResponse;
use Zenmanage\Cache\CacheInterface;
use Zenmanage\Flags\DefaultsCollection;
use Zenmanage\Flags\FlagManager;
use Zenmanage\Rules\RuleEngineInterface;

final class FlagManagerDefaultsTest extends TestCase
{
    private ApiClientInterface $apiClient;

    private CacheInterface $cache;

    private RuleEngineInterface $ruleEngine;

    protected function setUp(): void
    {
        $this->apiClient = Mockery::mock(ApiClientInterface::class);
        $this->cache = Mockery::mock(CacheInterface::class);
        $this->ruleEngine = Mockery::mock(RuleEngineInterface::class);

        // Mock cache miss and empty API response
        $this->cache->shouldReceive('get')->andReturn(null);
        $this->apiClient->shouldReceive('getRules')->andReturn(
            new RulesResponse(version: '1.0.0', flags: [])
        );
        $this->cache->shouldReceive('set')->andReturn(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function createFlagManager(): FlagManager
    {
        return new FlagManager(
            apiClient: $this->apiClient,
            cache: $this->cache,
            ruleEngine: $this->ruleEngine,
            cacheTtl: 3600,
            logger: new NullLogger()
        );
    }

    public function testSingleWithInlineDefault(): void
    {
        $this->apiClient->shouldReceive('reportUsage')->with('non-existent-flag', Mockery::any())->andReturnNull();

        $manager = $this->createFlagManager();

        // Test inline default
        $flag = $manager->single('non-existent-flag', 'inline-default-value');

        $this->assertSame('non-existent-flag', $flag->getKey());
        $this->assertSame('string', $flag->getType());
        $this->assertSame('inline-default-value', $flag->asString());
    }

    public function testSingleInlineDefaultTakesPriorityOverCollection(): void
    {
        $this->apiClient->shouldReceive('reportUsage')->with('test-flag', Mockery::any())->andReturnNull();

        $defaults = DefaultsCollection::fromArray([
            'test-flag' => 'collection-default',
        ]);

        $manager = $this->createFlagManager();

        // Inline default should take priority
        $flag = $manager->withDefaults($defaults)
            ->single('test-flag', 'inline-default');

        $this->assertSame('inline-default', $flag->asString());
    }

    public function testSingleFallsBackToCollectionWhenNoInlineDefault(): void
    {
        $this->apiClient->shouldReceive('reportUsage')->with('test-flag', Mockery::any())->andReturnNull();

        $defaults = DefaultsCollection::fromArray([
            'test-flag' => 'collection-default',
        ]);

        $manager = $this->createFlagManager();

        // Should use collection default when no inline default
        $flag = $manager->withDefaults($defaults)
            ->single('test-flag');

        $this->assertSame('collection-default', $flag->asString());
    }

    public function testSingleWithDifferentTypes(): void
    {
        $this->apiClient->shouldReceive('reportUsage')->with('bool-flag', Mockery::any())->andReturnNull();
        $this->apiClient->shouldReceive('reportUsage')->with('num-flag', Mockery::any())->andReturnNull();
        $this->apiClient->shouldReceive('reportUsage')->with('str-flag', Mockery::any())->andReturnNull();

        $manager = $this->createFlagManager();

        // Boolean default
        $boolFlag = $manager->single('bool-flag', true);
        $this->assertTrue($boolFlag->asBool());
        $this->assertSame('boolean', $boolFlag->getType());

        // Number default
        $numFlag = $manager->single('num-flag', 42);
        $this->assertSame(42, $numFlag->asNumber());
        $this->assertSame('number', $numFlag->getType());

        // String default
        $strFlag = $manager->single('str-flag', 'test');
        $this->assertSame('test', $strFlag->asString());
        $this->assertSame('string', $strFlag->getType());
    }
}
