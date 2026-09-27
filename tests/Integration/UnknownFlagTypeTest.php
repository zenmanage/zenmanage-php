<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Integration;

use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Zenmanage\Api\ApiClientInterface;
use Zenmanage\Api\Response\RulesResponse;
use Zenmanage\Cache\CacheInterface;
use Zenmanage\Exception\EvaluationException;
use Zenmanage\Flags\DefaultsCollection;
use Zenmanage\Flags\FlagManager;
use Zenmanage\Rules\RuleEngine;

/**
 * ZEN-1667: a rules payload may include a flag type this SDK release doesn't
 * know how to evaluate yet (the API has, historically, gained new flag types
 * — boolean/string/number were joined by `json` in ZEN-1383). An old SDK
 * release parsing a payload with a flag type from a newer release must not
 * throw or crash — it must degrade the unknown flag to the caller's default
 * while leaving every other flag in the payload unaffected.
 *
 * This test uses a hypothetical still-unknown `duration` type (not `json`,
 * which this SDK now supports natively — see JsonFlagTypeTest) to keep
 * covering the unknown-type degradation path itself.
 *
 * This test parses a full rules payload (through the real RulesResponse /
 * Flag parsing and the real RuleEngine, not mocks) to confirm end to end
 * behavior, not just that individual parsing methods don't throw.
 */
final class UnknownFlagTypeTest extends TestCase
{
    private \Mockery\MockInterface $apiClient;

    private \Mockery\MockInterface $cache;

    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * @return \Mockery\Expectation
     */
    private function expectReceive(\Mockery\MockInterface $mock, string $method)
    {
        /** @var \Mockery\Expectation $expectation */
        $expectation = $mock->shouldReceive($method);

        return $expectation;
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesPayloadWithUnknownTypeFlag(): array
    {
        return [
            'version' => '2026-09-23',
            'flags' => [
                [
                    'version' => 'fla_bool_001',
                    'type' => 'boolean',
                    'key' => 'dark-mode',
                    'name' => 'Dark Mode',
                    'target' => [
                        'version' => 'tar_bool_001',
                        'expired_at' => null,
                        'published_at' => '2026-09-01T00:00:00+00:00',
                        'scheduled_at' => null,
                        'value' => [
                            'version' => 'val_bool_001',
                            'value' => ['boolean' => true],
                        ],
                    ],
                    'rules' => [],
                ],
                [
                    'version' => 'fla_str_001',
                    'type' => 'string',
                    'key' => 'greeting',
                    'name' => 'Greeting',
                    'target' => [
                        'version' => 'tar_str_001',
                        'expired_at' => null,
                        'published_at' => '2026-09-01T00:00:00+00:00',
                        'scheduled_at' => null,
                        'value' => [
                            'version' => 'val_str_001',
                            'value' => ['string' => 'hello'],
                        ],
                    ],
                    'rules' => [],
                ],
                [
                    'version' => 'fla_num_001',
                    'type' => 'number',
                    'key' => 'max-items',
                    'name' => 'Max Items',
                    'target' => [
                        'version' => 'tar_num_001',
                        'expired_at' => null,
                        'published_at' => '2026-09-01T00:00:00+00:00',
                        'scheduled_at' => null,
                        'value' => [
                            'version' => 'val_num_001',
                            'value' => ['number' => 42],
                        ],
                    ],
                    'rules' => [],
                ],
                [
                    // A flag type this SDK release doesn't know about yet (a
                    // hypothetical future `duration` type). The value wrapper key
                    // ("duration") is also unrecognized.
                    'version' => 'fla_dur_001',
                    'type' => 'duration',
                    'key' => 'feature-config',
                    'name' => 'Feature Config',
                    'target' => [
                        'version' => 'tar_dur_001',
                        'expired_at' => null,
                        'published_at' => '2026-09-01T00:00:00+00:00',
                        'scheduled_at' => null,
                        'value' => [
                            'version' => 'val_dur_001',
                            'value' => ['duration' => '30s'],
                        ],
                    ],
                    'rules' => [],
                ],
            ],
        ];
    }

    private function createManager(?DefaultsCollection $defaults = null): FlagManager
    {
        $this->apiClient = Mockery::mock(ApiClientInterface::class);
        $this->cache = Mockery::mock(CacheInterface::class);

        $this->expectReceive($this->cache, 'get')->andReturn(null);
        $this->expectReceive($this->cache, 'set')->andReturn(true);
        $this->expectReceive($this->apiClient, 'reportUsage')->andReturnNull();

        $response = RulesResponse::fromArray($this->rulesPayloadWithUnknownTypeFlag());
        $this->expectReceive($this->apiClient, 'getRules')->andReturn($response);

        /** @var \Zenmanage\Api\ApiClientInterface $apiClient */
        $apiClient = $this->apiClient;
        /** @var \Zenmanage\Cache\CacheInterface $cache */
        $cache = $this->cache;

        $manager = new FlagManager(
            apiClient: $apiClient,
            cache: $cache,
            ruleEngine: new RuleEngine(),
            cacheTtl: 3600,
            logger: new NullLogger(),
        );

        if ($defaults !== null) {
            $manager = $manager->withDefaults($defaults);
        }

        return $manager;
    }

    public function testOtherFlagsStillEvaluateCorrectlyAlongsideAnUnknownFlagType(): void
    {
        $manager = $this->createManager();

        $this->assertTrue($manager->single('dark-mode')->asBool());
        $this->assertSame('hello', $manager->single('greeting')->asString());
        $this->assertSame(42, $manager->single('max-items')->asNumber());
    }

    public function testUnknownFlagTypeResolvesToInlineCallSiteDefaultInsteadOfThrowingOrMisparsing(): void
    {
        $manager = $this->createManager();

        $flag = $manager->single('feature-config', 'fallback-config');

        $this->assertSame('fallback-config', $flag->asString());
    }

    public function testUnknownFlagTypeResolvesToCollectionDefaultInsteadOfThrowingOrMisparsing(): void
    {
        $defaults = DefaultsCollection::fromArray(['feature-config' => 'collection-fallback']);
        $manager = $this->createManager($defaults);

        $flag = $manager->single('feature-config');

        $this->assertSame('collection-fallback', $flag->asString());
    }

    public function testUnknownFlagTypeWithoutAnyDefaultBehavesLikeAnyOtherMissingFlag(): void
    {
        $manager = $this->createManager();

        // No inline default and no DefaultsCollection entry: this must behave
        // exactly like looking up a flag key that isn't in the payload at all
        // (a documented EvaluationException), never an unhandled type error.
        $this->expectException(EvaluationException::class);
        $manager->single('feature-config');
    }

    public function testAllOmitsTheUnknownTypeFlagButEvaluatesEveryOtherFlag(): void
    {
        $manager = $this->createManager();

        $flags = $manager->all();
        $byKey = [];
        foreach ($flags as $flag) {
            $byKey[$flag->getKey()] = $flag;
        }

        $this->assertArrayNotHasKey('feature-config', $byKey);
        $this->assertTrue($byKey['dark-mode']->asBool());
        $this->assertSame('hello', $byKey['greeting']->asString());
        $this->assertSame(42, $byKey['max-items']->asNumber());
    }

    public function testAllFallsBackToDefaultsCollectionEntryForTheUnknownTypeFlag(): void
    {
        $defaults = DefaultsCollection::fromArray(['feature-config' => 'collection-fallback']);
        $manager = $this->createManager($defaults);

        $flags = $manager->all();
        $byKey = [];
        foreach ($flags as $flag) {
            $byKey[$flag->getKey()] = $flag;
        }

        $this->assertSame('collection-fallback', $byKey['feature-config']->asString());
        $this->assertTrue($byKey['dark-mode']->asBool());
        $this->assertSame('hello', $byKey['greeting']->asString());
        $this->assertSame(42, $byKey['max-items']->asNumber());
    }
}
