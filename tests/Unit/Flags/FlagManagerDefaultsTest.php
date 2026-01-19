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
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testSingleWithInlineDefault(): void
    {
        $apiClient = Mockery::mock(ApiClientInterface::class);
        $cache = Mockery::mock(CacheInterface::class);
        $ruleEngine = Mockery::mock(RuleEngineInterface::class);

        // Mock cache miss and empty API response
        $cache->shouldReceive('get')->andReturn(null);
        $apiClient->shouldReceive('getRules')->andReturn(
            new RulesResponse(version: '1.0.0', flags: [])
        );
        $cache->shouldReceive('set')->andReturn(true);
        $apiClient->shouldReceive('reportUsage')->with('non-existent-flag', Mockery::any())->andReturnNull();

        $manager = new FlagManager(
            apiClient: $apiClient,
            cache: $cache,
            ruleEngine: $ruleEngine,
            cacheTtl: 3600,
            logger: new NullLogger()
        );

        // Test inline default
        $flag = $manager->single('non-existent-flag', 'inline-default-value');

        $this->assertSame('non-existent-flag', $flag->getKey());
        $this->assertSame('string', $flag->getType());
        $this->assertSame('inline-default-value', $flag->asString());
    }

    public function testSingleInlineDefaultTakesPriorityOverCollection(): void
    {
        $apiClient = Mockery::mock(ApiClientInterface::class);
        $cache = Mockery::mock(CacheInterface::class);
        $ruleEngine = Mockery::mock(RuleEngineInterface::class);

        // Mock cache miss and empty API response
        $cache->shouldReceive('get')->andReturn(null);
        $apiClient->shouldReceive('getRules')->andReturn(
            new RulesResponse(version: '1.0.0', flags: [])
        );
        $cache->shouldReceive('set')->andReturn(true);
        $apiClient->shouldReceive('reportUsage')->with('test-flag', Mockery::any())->andReturnNull();

        $defaults = DefaultsCollection::fromArray([
            'test-flag' => 'collection-default',
        ]);

        $manager = new FlagManager(
            apiClient: $apiClient,
            cache: $cache,
            ruleEngine: $ruleEngine,
            cacheTtl: 3600,
            logger: new NullLogger()
        );

        // Inline default should take priority
        $flag = $manager->withDefaults($defaults)
            ->single('test-flag', 'inline-default');

        $this->assertSame('inline-default', $flag->asString());
    }

    public function testSingleFallsBackToCollectionWhenNoInlineDefault(): void
    {
        $apiClient = Mockery::mock(ApiClientInterface::class);
        $cache = Mockery::mock(CacheInterface::class);
        $ruleEngine = Mockery::mock(RuleEngineInterface::class);

        // Mock cache miss and empty API response
        $cache->shouldReceive('get')->andReturn(null);
        $apiClient->shouldReceive('getRules')->andReturn(
            new RulesResponse(version: '1.0.0', flags: [])
        );
        $cache->shouldReceive('set')->andReturn(true);
        $apiClient->shouldReceive('reportUsage')->with('test-flag', Mockery::any())->andReturnNull();

        $defaults = DefaultsCollection::fromArray([
            'test-flag' => 'collection-default',
        ]);

        $manager = new FlagManager(
            apiClient: $apiClient,
            cache: $cache,
            ruleEngine: $ruleEngine,
            cacheTtl: 3600,
            logger: new NullLogger()
        );

        // Should use collection default when no inline default
        $flag = $manager->withDefaults($defaults)
            ->single('test-flag');

        $this->assertSame('collection-default', $flag->asString());
    }

    public function testSingleWithDifferentTypes(): void
    {
        $apiClient = Mockery::mock(ApiClientInterface::class);
        $cache = Mockery::mock(CacheInterface::class);
        $ruleEngine = Mockery::mock(RuleEngineInterface::class);

        // Mock cache miss and empty API response
        $cache->shouldReceive('get')->andReturn(null);
        $apiClient->shouldReceive('getRules')->andReturn(
            new RulesResponse(version: '1.0.0', flags: [])
        );
        $cache->shouldReceive('set')->andReturn(true);
        $apiClient->shouldReceive('reportUsage')->with('bool-flag', Mockery::any())->andReturnNull();
        $apiClient->shouldReceive('reportUsage')->with('num-flag', Mockery::any())->andReturnNull();
        $apiClient->shouldReceive('reportUsage')->with('str-flag', Mockery::any())->andReturnNull();

        $manager = new FlagManager(
            apiClient: $apiClient,
            cache: $cache,
            ruleEngine: $ruleEngine,
            cacheTtl: 3600,
            logger: new NullLogger()
        );

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
