<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Flags;

use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Zenmanage\Api\ApiClientInterface;
use Zenmanage\Api\Response\RulesResponse;
use Zenmanage\Cache\CacheInterface;
use Zenmanage\Flags\Context\Attribute;
use Zenmanage\Flags\Context\Context;
use Zenmanage\Flags\Flag;
use Zenmanage\Flags\FlagManager;
use Zenmanage\Flags\Rollout;
use Zenmanage\Flags\Target;
use Zenmanage\Rules\RuleEngine;

/**
 * Integration tests for FlagManager with percentage rollouts.
 *
 * Uses the real RuleEngine (not mocked) so that the full evaluation flow
 * including rollout bucketing + rule evaluation is exercised.
 */
final class FlagManagerRolloutTest extends TestCase
{
    private \Mockery\MockInterface $apiClient;

    private \Mockery\MockInterface $cache;

    private RuleEngine $ruleEngine;

    protected function setUp(): void
    {
        $this->apiClient = Mockery::mock(ApiClientInterface::class);

        /** @var \Mockery\Expectation $reportUsage */
        $reportUsage = $this->apiClient->shouldReceive('reportUsage');
        $reportUsage->byDefault();

        $this->cache = Mockery::mock(CacheInterface::class);
        $this->ruleEngine = new RuleEngine();
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * @param array<string, mixed>[] $flagArrays
     */
    private function cacheWith(array $flagArrays): void
    {
        $payload = json_encode([
            'version' => '2026-02-24',
            'flags' => $flagArrays,
        ]);

        /** @var \Mockery\Expectation $cacheGet */
        $cacheGet = $this->cache->shouldReceive('get');
        $cacheGet->with('zenmanage_rules')
            ->andReturn($payload);
    }

    private function createManager(): FlagManager
    {
        /** @var \Zenmanage\Api\ApiClientInterface $apiClient */
        $apiClient = $this->apiClient;
        /** @var \Zenmanage\Cache\CacheInterface $cache */
        $cache = $this->cache;

        return new FlagManager(
            apiClient: $apiClient,
            cache: $cache,
            ruleEngine: $this->ruleEngine,
            cacheTtl: 3600,
            logger: new NullLogger(),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function baseFlagArray(array $overrides = []): array
    {
        return array_merge([
            'version' => 'fla_test',
            'type' => 'boolean',
            'key' => 'test-flag',
            'name' => 'Test Flag',
            'target' => [
                'version' => 'tar_fallback',
                'expired_at' => null,
                'published_at' => '2026-02-20T00:00:00+00:00',
                'scheduled_at' => null,
                'value' => [
                    'version' => 'val_fallback',
                    'value' => ['boolean' => false],
                ],
            ],
            'rules' => [],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rolloutArray(array $overrides = []): array
    {
        return array_merge([
            'target' => [
                'version' => 'tar_rollout',
                'expired_at' => null,
                'published_at' => '2026-02-24T00:00:00+00:00',
                'scheduled_at' => null,
                'value' => [
                    'version' => 'val_rollout',
                    'value' => ['boolean' => true],
                ],
            ],
            'rules' => [],
            'percentage' => 50,
            'salt' => 'test-salt',
            'status' => 'active',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Flag without rollout (regression)
    // -------------------------------------------------------------------------

    public function testEvaluatesNormallyWhenNoRollout(): void
    {
        $this->cacheWith([$this->baseFlagArray(['key' => 'no-rollout'])]);

        $flag = $this->createManager()->single('no-rollout');
        $this->assertFalse($flag->asBool());
    }

    public function testEvaluatesRulesNormallyWhenNoRollout(): void
    {
        $flagData = $this->baseFlagArray([
            'key' => 'rules-no-rollout',
            'rules' => [
                [
                    'version' => 'rul_1',
                    'description' => 'Country rule',
                    'criteria' => [
                        'selector' => 'attribute',
                        'selector_subtype' => 'country',
                        'comparer' => 'equal',
                        'values' => [['identifier' => 'US']],
                    ],
                    'position' => 1,
                    'value' => [
                        'version' => 'val_rule',
                        'value' => ['boolean' => true],
                    ],
                ],
            ],
        ]);

        $this->cacheWith([$flagData]);

        $context = new Context('user', null, 'user-1', [
            new Attribute('country', ['US']),
        ]);

        $flag = $this->createManager()->withContext($context)->single('rules-no-rollout');
        $this->assertTrue($flag->asBool());
    }

    // -------------------------------------------------------------------------
    // Flag with active rollout — basic bucketing
    // -------------------------------------------------------------------------

    public function testServesRolloutValueWhenContextInBucket(): void
    {
        // test-salt + user-0 => bucket 34 => 34 < 50 => IN bucket
        $flagData = $this->baseFlagArray([
            'key' => 'rollout-flag',
            'rollout' => $this->rolloutArray(),
        ]);

        $this->cacheWith([$flagData]);

        $context = Context::single('user', 'user-0');
        $flag = $this->createManager()->withContext($context)->single('rollout-flag');

        $this->assertTrue($flag->asBool()); // rollout value
    }

    public function testServesFallbackValueWhenContextOutsideBucket(): void
    {
        // test-salt + user-2 => bucket 98 => 98 >= 50 => NOT in bucket
        $flagData = $this->baseFlagArray([
            'key' => 'rollout-flag',
            'rollout' => $this->rolloutArray(),
        ]);

        $this->cacheWith([$flagData]);

        $context = Context::single('user', 'user-2');
        $flag = $this->createManager()->withContext($context)->single('rollout-flag');

        $this->assertFalse($flag->asBool()); // fallback value
    }

    public function testServesFallbackWhenNoContextIdentifier(): void
    {
        // No identifier => isInBucket returns false => fallback
        $flagData = $this->baseFlagArray([
            'key' => 'rollout-flag',
            'rollout' => $this->rolloutArray(['percentage' => 100]),
        ]);

        $this->cacheWith([$flagData]);

        $context = new Context('anonymous');
        $flag = $this->createManager()->withContext($context)->single('rollout-flag');

        // Even at 100%, null identifier => fallback
        $this->assertFalse($flag->asBool());
    }

    public function testServesRolloutToAllContextsAtHundredPercent(): void
    {
        $flagData = $this->baseFlagArray([
            'key' => 'full-rollout',
            'rollout' => $this->rolloutArray(['salt' => 'any-salt', 'percentage' => 100]),
        ]);

        $this->cacheWith([$flagData]);

        foreach (['user-1', 'user-2', 'user-3', 'something-else'] as $id) {
            $context = Context::single('user', $id);
            $flag = $this->createManager()->withContext($context)->single('full-rollout');
            $this->assertTrue($flag->asBool(), "Expected rollout value for {$id}");
        }
    }

    public function testServesFallbackToAllContextsAtZeroPercent(): void
    {
        $flagData = $this->baseFlagArray([
            'key' => 'zero-rollout',
            'rollout' => $this->rolloutArray(['salt' => 'any-salt', 'percentage' => 0]),
        ]);

        $this->cacheWith([$flagData]);

        foreach (['user-1', 'user-2', 'user-3'] as $id) {
            $context = Context::single('user', $id);
            $flag = $this->createManager()->withContext($context)->single('zero-rollout');
            $this->assertFalse($flag->asBool(), "Expected fallback value for {$id}");
        }
    }

    // -------------------------------------------------------------------------
    // Rollout with rules
    // -------------------------------------------------------------------------

    public function testEvaluatesRolloutRulesWhenInBucket(): void
    {
        // test-salt + user-0 => bucket 34, in bucket at 50%
        $flagData = $this->baseFlagArray([
            'key' => 'rollout-rules',
            'type' => 'string',
            'target' => [
                'version' => 'tar_fallback',
                'expired_at' => null,
                'published_at' => null,
                'scheduled_at' => null,
                'value' => ['version' => 'v1', 'value' => ['string' => 'fallback-value']],
            ],
            'rules' => [
                [
                    'version' => 'rul_fb',
                    'description' => 'Fallback rule',
                    'criteria' => [
                        'selector' => 'attribute',
                        'selector_subtype' => 'country',
                        'comparer' => 'equal',
                        'values' => [['identifier' => 'US']],
                    ],
                    'position' => 1,
                    'value' => ['version' => 'v1', 'value' => ['string' => 'fallback-rule-match']],
                ],
            ],
            'rollout' => [
                'target' => [
                    'version' => 'tar_rollout',
                    'expired_at' => null,
                    'published_at' => null,
                    'scheduled_at' => null,
                    'value' => ['version' => 'v1', 'value' => ['string' => 'rollout-value']],
                ],
                'rules' => [
                    [
                        'version' => 'rul_ro',
                        'description' => 'Rollout rule',
                        'criteria' => [
                            'selector' => 'attribute',
                            'selector_subtype' => 'country',
                            'comparer' => 'equal',
                            'values' => [['identifier' => 'US']],
                        ],
                        'position' => 1,
                        'value' => ['version' => 'v1', 'value' => ['string' => 'rollout-rule-match']],
                    ],
                ],
                'percentage' => 50,
                'salt' => 'test-salt',
                'status' => 'active',
            ],
        ]);

        $this->cacheWith([$flagData]);

        $context = Context::single('user', 'user-0');
        $context->addAttribute(new Attribute('country', ['US']));

        $flag = $this->createManager()->withContext($context)->single('rollout-rules');
        $this->assertSame('rollout-rule-match', $flag->asString());
    }

    public function testEvaluatesFallbackRulesWhenOutsideBucket(): void
    {
        // test-salt + user-2 => bucket 98, NOT in bucket at 50%
        $flagData = $this->baseFlagArray([
            'key' => 'rollout-rules',
            'type' => 'string',
            'target' => [
                'version' => 'tar_fallback',
                'expired_at' => null,
                'published_at' => null,
                'scheduled_at' => null,
                'value' => ['version' => 'v1', 'value' => ['string' => 'fallback-value']],
            ],
            'rules' => [
                [
                    'version' => 'rul_fb',
                    'description' => 'Fallback rule',
                    'criteria' => [
                        'selector' => 'attribute',
                        'selector_subtype' => 'country',
                        'comparer' => 'equal',
                        'values' => [['identifier' => 'US']],
                    ],
                    'position' => 1,
                    'value' => ['version' => 'v1', 'value' => ['string' => 'fallback-rule-match']],
                ],
            ],
            'rollout' => [
                'target' => [
                    'version' => 'tar_rollout',
                    'expired_at' => null,
                    'published_at' => null,
                    'scheduled_at' => null,
                    'value' => ['version' => 'v1', 'value' => ['string' => 'rollout-value']],
                ],
                'rules' => [
                    [
                        'version' => 'rul_ro',
                        'description' => 'Rollout rule',
                        'criteria' => [
                            'selector' => 'attribute',
                            'selector_subtype' => 'country',
                            'comparer' => 'equal',
                            'values' => [['identifier' => 'US']],
                        ],
                        'position' => 1,
                        'value' => ['version' => 'v1', 'value' => ['string' => 'rollout-rule-match']],
                    ],
                ],
                'percentage' => 50,
                'salt' => 'test-salt',
                'status' => 'active',
            ],
        ]);

        $this->cacheWith([$flagData]);

        $context = Context::single('user', 'user-2');
        $context->addAttribute(new Attribute('country', ['US']));

        $flag = $this->createManager()->withContext($context)->single('rollout-rules');
        $this->assertSame('fallback-rule-match', $flag->asString());
    }

    public function testUsesRolloutTargetWhenInBucketButNoRulesMatch(): void
    {
        // test-salt + user-0 => bucket 34, in bucket at 50%
        $flagData = $this->baseFlagArray([
            'key' => 'rollout-no-match',
            'type' => 'string',
            'target' => [
                'version' => 'tar_fb',
                'expired_at' => null,
                'published_at' => null,
                'scheduled_at' => null,
                'value' => ['version' => 'v1', 'value' => ['string' => 'fallback-value']],
            ],
            'rollout' => [
                'target' => [
                    'version' => 'tar_ro',
                    'expired_at' => null,
                    'published_at' => null,
                    'scheduled_at' => null,
                    'value' => ['version' => 'v1', 'value' => ['string' => 'rollout-value']],
                ],
                'rules' => [
                    [
                        'version' => 'rul_ro',
                        'description' => 'Japan only',
                        'criteria' => [
                            'selector' => 'attribute',
                            'selector_subtype' => 'country',
                            'comparer' => 'equal',
                            'values' => [['identifier' => 'JP']],
                        ],
                        'position' => 1,
                        'value' => ['version' => 'v1', 'value' => ['string' => 'rollout-rule-match']],
                    ],
                ],
                'percentage' => 50,
                'salt' => 'test-salt',
                'status' => 'active',
            ],
        ]);

        $this->cacheWith([$flagData]);

        $context = Context::single('user', 'user-0');
        $context->addAttribute(new Attribute('country', ['US'])); // Won't match JP

        $flag = $this->createManager()->withContext($context)->single('rollout-no-match');
        $this->assertSame('rollout-value', $flag->asString());
    }

    public function testUsesFallbackTargetWhenOutsideBucketAndNoRulesMatch(): void
    {
        // test-salt + user-2 => bucket 98, NOT in bucket at 50%
        $flagData = $this->baseFlagArray([
            'key' => 'fb-no-match',
            'type' => 'string',
            'target' => [
                'version' => 'tar_fb',
                'expired_at' => null,
                'published_at' => null,
                'scheduled_at' => null,
                'value' => ['version' => 'v1', 'value' => ['string' => 'fallback-value']],
            ],
            'rules' => [
                [
                    'version' => 'rul_fb',
                    'description' => 'Japan only',
                    'criteria' => [
                        'selector' => 'attribute',
                        'selector_subtype' => 'country',
                        'comparer' => 'equal',
                        'values' => [['identifier' => 'JP']],
                    ],
                    'position' => 1,
                    'value' => ['version' => 'v1', 'value' => ['string' => 'fallback-rule-match']],
                ],
            ],
            'rollout' => [
                'target' => [
                    'version' => 'tar_ro',
                    'expired_at' => null,
                    'published_at' => null,
                    'scheduled_at' => null,
                    'value' => ['version' => 'v1', 'value' => ['string' => 'rollout-value']],
                ],
                'rules' => [],
                'percentage' => 50,
                'salt' => 'test-salt',
                'status' => 'active',
            ],
        ]);

        $this->cacheWith([$flagData]);

        $context = Context::single('user', 'user-2');
        $context->addAttribute(new Attribute('country', ['US'])); // Won't match JP

        $flag = $this->createManager()->withContext($context)->single('fb-no-match');
        $this->assertSame('fallback-value', $flag->asString());
    }

    // -------------------------------------------------------------------------
    // Different flag types
    // -------------------------------------------------------------------------

    public function testHandlesStringFlagRollout(): void
    {
        $flagData = $this->baseFlagArray([
            'key' => 'string-rollout',
            'type' => 'string',
            'target' => [
                'version' => 'tar_fb',
                'expired_at' => null,
                'published_at' => null,
                'scheduled_at' => null,
                'value' => ['version' => 'v1', 'value' => ['string' => 'old-variant']],
            ],
            'rollout' => [
                'target' => [
                    'version' => 'tar_ro',
                    'expired_at' => null,
                    'published_at' => null,
                    'scheduled_at' => null,
                    'value' => ['version' => 'v1', 'value' => ['string' => 'new-variant']],
                ],
                'rules' => [],
                'percentage' => 50,
                'salt' => 'test-salt',
                'status' => 'active',
            ],
        ]);

        $this->cacheWith([$flagData]);

        // user-0 => bucket 34, in bucket
        $in = $this->createManager()
            ->withContext(Context::single('user', 'user-0'))
            ->single('string-rollout');
        $this->assertSame('new-variant', $in->asString());

        // user-2 => bucket 98, out of bucket
        $out = $this->createManager()
            ->withContext(Context::single('user', 'user-2'))
            ->single('string-rollout');
        $this->assertSame('old-variant', $out->asString());
    }

    public function testHandlesNumberFlagRollout(): void
    {
        $flagData = $this->baseFlagArray([
            'key' => 'number-rollout',
            'type' => 'number',
            'target' => [
                'version' => 'tar_fb',
                'expired_at' => null,
                'published_at' => null,
                'scheduled_at' => null,
                'value' => ['version' => 'v1', 'value' => ['number' => 1]],
            ],
            'rollout' => [
                'target' => [
                    'version' => 'tar_ro',
                    'expired_at' => null,
                    'published_at' => null,
                    'scheduled_at' => null,
                    'value' => ['version' => 'v1', 'value' => ['number' => 2]],
                ],
                'rules' => [],
                'percentage' => 50,
                'salt' => 'test-salt',
                'status' => 'active',
            ],
        ]);

        $this->cacheWith([$flagData]);

        // user-0 => bucket 34, in bucket
        $in = $this->createManager()
            ->withContext(Context::single('user', 'user-0'))
            ->single('number-rollout');
        $this->assertSame(2, $in->asNumber());

        // user-2 => bucket 98, out of bucket
        $out = $this->createManager()
            ->withContext(Context::single('user', 'user-2'))
            ->single('number-rollout');
        $this->assertSame(1, $out->asNumber());
    }

    // -------------------------------------------------------------------------
    // all() with rollouts
    // -------------------------------------------------------------------------

    public function testAllEvaluatesRolloutsForEachFlag(): void
    {
        $flags = [
            $this->baseFlagArray([
                'key' => 'flag-with-rollout',
                'rollout' => $this->rolloutArray(),
            ]),
            $this->baseFlagArray([
                'key' => 'flag-without-rollout',
                'target' => [
                    'version' => 'tar_normal',
                    'expired_at' => null,
                    'published_at' => null,
                    'scheduled_at' => null,
                    'value' => ['version' => 'v1', 'value' => ['boolean' => true]],
                ],
            ]),
        ];

        $this->cacheWith($flags);

        // user-0 => bucket 34, in bucket at 50%
        $context = Context::single('user', 'user-0');
        $allFlags = $this->createManager()->withContext($context)->all();

        $this->assertCount(2, $allFlags);

        $rolloutFlag = null;
        $normalFlag = null;
        foreach ($allFlags as $f) {
            if ($f->getKey() === 'flag-with-rollout') {
                $rolloutFlag = $f;
            }
            if ($f->getKey() === 'flag-without-rollout') {
                $normalFlag = $f;
            }
        }

        $this->assertNotNull($rolloutFlag);
        $this->assertNotNull($normalFlag);
        $this->assertTrue($rolloutFlag->asBool());  // rollout value
        $this->assertTrue($normalFlag->asBool());   // normal target
    }

    // -------------------------------------------------------------------------
    // Serialization round-trip
    // -------------------------------------------------------------------------

    public function testRolloutDataPreservedThroughFromArrayAndJsonSerialize(): void
    {
        $flagData = $this->baseFlagArray([
            'key' => 'serialized-rollout',
            'rollout' => $this->rolloutArray(),
        ]);

        $flag = Flag::fromArray($flagData);
        $this->assertNotNull($flag->getRollout());
        $this->assertSame(50, $flag->getRollout()->getPercentage());
        $this->assertSame('test-salt', $flag->getRollout()->getSalt());
        $this->assertSame('active', $flag->getRollout()->getStatus());

        $json = $flag->jsonSerialize();
        $this->assertArrayHasKey('rollout', $json);

        /** @var array<string, mixed> $rolloutJson */
        $rolloutJson = $json['rollout'];
        $this->assertSame(50, $rolloutJson['percentage']);
        $this->assertSame('test-salt', $rolloutJson['salt']);
    }

    public function testRolloutAbsentInJsonWhenNotPresent(): void
    {
        $flagData = $this->baseFlagArray(['key' => 'no-rollout']);
        $flag = Flag::fromArray($flagData);
        $json = $flag->jsonSerialize();

        $this->assertArrayNotHasKey('rollout', $json);
    }

    public function testFlagsWithRolloutsLoadCorrectlyFromCache(): void
    {
        // Simulates the full cache round-trip
        $flagData = $this->baseFlagArray([
            'key' => 'cached-rollout',
            'rollout' => $this->rolloutArray(['salt' => 'test-salt', 'percentage' => 50]),
        ]);

        $this->cacheWith([$flagData]);

        // user-0 => bucket 34 < 50 => in bucket
        $context = Context::single('user', 'user-0');
        $flag = $this->createManager()->withContext($context)->single('cached-rollout');
        $this->assertTrue($flag->asBool());
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function testRolloutWithEmptyRulesArray(): void
    {
        $flagData = $this->baseFlagArray([
            'key' => 'empty-rules-rollout',
            'rollout' => $this->rolloutArray(['rules' => []]),
        ]);

        $this->cacheWith([$flagData]);

        $context = Context::single('user', 'user-0'); // in bucket
        $flag = $this->createManager()->withContext($context)->single('empty-rules-rollout');
        $this->assertTrue($flag->asBool());
    }

    public function testDefaultAnonymousContextFallsBack(): void
    {
        $flagData = $this->baseFlagArray([
            'key' => 'default-context',
            'rollout' => $this->rolloutArray(['percentage' => 100]),
        ]);

        $this->cacheWith([$flagData]);

        // Default context is anonymous with no identifier
        $flag = $this->createManager()->single('default-context');
        // Even at 100%, null identifier => fallback
        $this->assertFalse($flag->asBool());
    }

    public function testMultipleFlagsWithDifferentRolloutConfigs(): void
    {
        $flags = [
            $this->baseFlagArray([
                'key' => 'flag-a',
                'rollout' => $this->rolloutArray(['salt' => 'salt-a', 'percentage' => 10]),
            ]),
            $this->baseFlagArray([
                'key' => 'flag-b',
                'rollout' => $this->rolloutArray(['salt' => 'salt-b', 'percentage' => 90]),
            ]),
        ];

        $this->cacheWith($flags);

        $context = Context::single('user', 'test-user');
        $allFlags = $this->createManager()->withContext($context)->all();

        // Each flag should be evaluated independently with its own salt
        $this->assertCount(2, $allFlags);
    }

    // =========================================================================
    // Helpers — rule-based scenarios (parity coverage)
    // =========================================================================

    // Cross-SDK CRC32b rollout vectors (from RolloutBucketTest):
    //   salt=abc123, identifier=ctx-beta  → bucket 3  → IN  20% rollout
    //   salt=abc123, identifier=ctx-alpha → bucket 54 → NOT in 20% rollout
    private const P_SALT = 'abc123';
    private const P_C3   = 'ctx-beta';   // bucket 3  → IN
    private const P_C4   = 'ctx-alpha';  // bucket 54 → NOT in

    /** 
     * @param array<int, array<string, mixed>> $rules
     * @param array<string, mixed>|null $rollout
     * @return array<string, mixed>
     */
    private function boolFlagArray(string $key, bool $base, array $rules = [], ?array $rollout = null): array
    {
        $flag = [
            'version' => "fla_{$key}",
            'type' => 'boolean',
            'key' => $key,
            'name' => $key,
            'target' => [
                'version' => "tar_{$key}",
                'expired_at' => null,
                'published_at' => null,
                'scheduled_at' => null,
                'value' => ['version' => 'v1', 'value' => ['boolean' => $base]],
            ],
            'rules' => $rules,
        ];

        if ($rollout !== null) {
            $flag['rollout'] = $rollout;
        }

        return $flag;
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @return array<string, mixed>
     */
    private function stringFlagArray(string $key, string $base, array $rules = []): array
    {
        return [
            'version' => "fla_{$key}",
            'type' => 'string',
            'key' => $key,
            'name' => $key,
            'target' => [
                'version' => "tar_{$key}",
                'expired_at' => null,
                'published_at' => null,
                'scheduled_at' => null,
                'value' => ['version' => 'v1', 'value' => ['string' => $base]],
            ],
            'rules' => $rules,
        ];
    }

    /** @return array<string, mixed> */
    private function numberFlagArray(string $key, int|float $base): array
    {
        return [
            'version' => "fla_{$key}",
            'type' => 'number',
            'key' => $key,
            'name' => $key,
            'target' => [
                'version' => "tar_{$key}",
                'expired_at' => null,
                'published_at' => null,
                'scheduled_at' => null,
                'value' => ['version' => 'v1', 'value' => ['number' => $base]],
            ],
            'rules' => [],
        ];
    }

    /**
     * Build a rule array (for both segment and attribute selectors).
     *
     * @param array<int, array<string, mixed>> $values
     * @return array<string, mixed>
     */
    private function ruleFor(int $pos, string $selector, ?string $subtype, string $comparer, array $values, mixed $ruleValue, string $valueType = 'boolean'): array
    {
        return [
            'version' => "rul_{$pos}",
            'description' => "Rule {$pos}",
            'criteria' => [
                'selector' => $selector,
                'selector_subtype' => $subtype,
                'comparer' => $comparer,
                'values' => $values,
            ],
            'position' => $pos,
            'value' => ['version' => 'v1', 'value' => [$valueType => $ruleValue]],
        ];
    }

    /**
     * Build a rollout config with optional gate rules.
     *
     * @param array<int, array<string, mixed>> $rules
     * @return array<string, mixed>
     */
    private function parityRollout(int $percentage, string $salt, array $rules = []): array
    {
        return [
            'target' => [
                'version' => 'tar_rollout',
                'expired_at' => null,
                'published_at' => null,
                'scheduled_at' => null,
                'value' => ['version' => 'v1', 'value' => ['boolean' => true]],
            ],
            'rules' => $rules,
            'percentage' => $percentage,
            'salt' => $salt,
            'status' => 'active',
        ];
    }

    // ---- Cross-SDK parity context fixtures ----

    /** C1: user-us-free */
    private function parityC1(): Context
    {
        return new Context('user', 'Alice US Free', 'user-us-free', [
            new Attribute('country', ['US']),
            new Attribute('plan', ['free']),
            new Attribute('age', ['25']),
            new Attribute('email', ['alice@acme.com']),
            new Attribute('tier', ['1']),
            new Attribute('tags', ['alpha', 'beta']),
        ]);
    }

    /** C2: user-ca-pro */
    private function parityC2(): Context
    {
        return new Context('user', 'Bob CA Pro', 'user-ca-pro', [
            new Attribute('country', ['CA']),
            new Attribute('plan', ['pro']),
            new Attribute('age', ['42']),
            new Attribute('email', ['bob@acme.ca']),
            new Attribute('tier', ['3']),
            new Attribute('tags', ['beta', 'gamma']),
        ]);
    }

    /** C3: rollout-included — abc123 + ctx-beta → bucket 3 → IN 20% */
    private function parityC3(): Context
    {
        return new Context('user', 'Rollout In', self::P_C3, [
            new Attribute('country', ['US']),
        ]);
    }

    /** C4: rollout-excluded — abc123 + ctx-alpha → bucket 54 → NOT in 20% */
    private function parityC4(): Context
    {
        return new Context('user', 'Rollout Out', self::P_C4, [
            new Attribute('country', ['US']),
        ]);
    }

    /** C5: shared-123 as type=user */
    private function parityC5(): Context
    {
        return new Context('user', 'Shared User', 'shared-123');
    }

    /** C6: shared-123 as type=organization */
    private function parityC6(): Context
    {
        return new Context('organization', 'Shared Org', 'shared-123');
    }

    // =========================================================================
    // Static base values (real RuleEngine end-to-end, no rules)
    // =========================================================================

    public function testStaticBoolTrueBaseValueReturnsTrue(): void
    {
        $this->cacheWith([$this->boolFlagArray('static-on', true)]);

        $this->assertTrue($this->createManager()->single('static-on')->asBool());
    }

    public function testStaticStringBaseValueReturnsString(): void
    {
        $this->cacheWith([$this->stringFlagArray('static-str', 'control')]);

        $this->assertSame('control', $this->createManager()->single('static-str')->asString());
    }

    public function testStaticNumberBaseValueReturnsNumber(): void
    {
        $this->cacheWith([$this->numberFlagArray('static-num', 1500)]);

        $this->assertSame(1500.0, (float) $this->createManager()->single('static-num')->asNumber());
    }

    // =========================================================================
    // Segment (context identifier) rule evaluation
    // =========================================================================

    public function testSegmentRuleMatchesContextByIdentifier(): void
    {
        $this->cacheWith([$this->boolFlagArray('seg-eq', false, [
            $this->ruleFor(1, 'segment', null, 'equal', [['identifier' => 'user-us-free', 'type' => 'user']], true),
        ])]);

        $this->assertTrue($this->createManager()->withContext($this->parityC1())->single('seg-eq')->asBool());
    }

    public function testSegmentRuleDoesNotMatchDifferentIdentifier(): void
    {
        $this->cacheWith([$this->boolFlagArray('seg-eq', false, [
            $this->ruleFor(1, 'segment', null, 'equal', [['identifier' => 'user-us-free', 'type' => 'user']], true),
        ])]);

        $this->assertFalse($this->createManager()->withContext($this->parityC2())->single('seg-eq')->asBool());
    }

    /** Segment rule targets type=organization; context is type=user — must NOT match. */
    public function testSegmentRuleTypeStrictnessUserContextFails(): void
    {
        $this->cacheWith([$this->boolFlagArray('seg-type', false, [
            $this->ruleFor(1, 'segment', null, 'equal', [['identifier' => 'shared-123', 'type' => 'organization']], true),
        ])]);

        $this->assertFalse($this->createManager()->withContext($this->parityC5())->single('seg-type')->asBool());
    }

    /** Segment rule targets type=organization; context is type=organization — must match. */
    public function testSegmentRuleTypeStrictnessOrgContextMatches(): void
    {
        $this->cacheWith([$this->boolFlagArray('seg-type', false, [
            $this->ruleFor(1, 'segment', null, 'equal', [['identifier' => 'shared-123', 'type' => 'organization']], true),
        ])]);

        $this->assertTrue($this->createManager()->withContext($this->parityC6())->single('seg-type')->asBool());
    }

    // =========================================================================
    // Attribute rule evaluation — list, string operators
    // =========================================================================

    public function testAttributeInListMatchesWhenValueInList(): void
    {
        $this->cacheWith([$this->boolFlagArray('attr-in', false, [
            $this->ruleFor(1, 'attribute', 'plan', 'in', [['identifier' => 'pro'], ['identifier' => 'enterprise']], true),
        ])]);

        $this->assertTrue($this->createManager()->withContext($this->parityC2())->single('attr-in')->asBool()); // plan=pro
    }

    public function testAttributeInListReturnsFalseWhenValueNotInList(): void
    {
        $this->cacheWith([$this->boolFlagArray('attr-in', false, [
            $this->ruleFor(1, 'attribute', 'plan', 'in', [['identifier' => 'pro'], ['identifier' => 'enterprise']], true),
        ])]);

        $this->assertFalse($this->createManager()->withContext($this->parityC1())->single('attr-in')->asBool()); // plan=free
    }

    public function testAttributeContainsMatchesWhenValueContained(): void
    {
        $this->cacheWith([$this->boolFlagArray('attr-contains', false, [
            $this->ruleFor(1, 'attribute', 'tags', 'contains', [['identifier' => 'beta']], true),
        ])]);

        $this->assertTrue($this->createManager()->withContext($this->parityC1())->single('attr-contains')->asBool()); // tags=[alpha,beta]
    }

    public function testAttributeEndsWithMatchesWhenSuffixPresent(): void
    {
        $this->cacheWith([$this->boolFlagArray('attr-ends', false, [
            $this->ruleFor(1, 'attribute', 'email', 'ends_with', [['identifier' => '@acme.com']], true),
        ])]);

        $this->assertTrue($this->createManager()->withContext($this->parityC1())->single('attr-ends')->asBool()); // alice@acme.com
    }

    public function testAttributeEndsWithReturnsFalseWhenSuffixAbsent(): void
    {
        $this->cacheWith([$this->boolFlagArray('attr-ends', false, [
            $this->ruleFor(1, 'attribute', 'email', 'ends_with', [['identifier' => '@acme.com']], true),
        ])]);

        $this->assertFalse($this->createManager()->withContext($this->parityC2())->single('attr-ends')->asBool()); // bob@acme.ca
    }

    // =========================================================================
    // Attribute rule evaluation — numeric operators
    // =========================================================================

    public function testNumericGteMatchesWhenAboveThreshold(): void
    {
        $this->cacheWith([$this->boolFlagArray('attr-gte', false, [
            $this->ruleFor(1, 'attribute', 'tier', 'gte', [['identifier' => '2']], true),
        ])]);

        $this->assertTrue($this->createManager()->withContext($this->parityC2())->single('attr-gte')->asBool()); // tier=3
    }

    public function testNumericGteReturnsFalseWhenBelowThreshold(): void
    {
        $this->cacheWith([$this->boolFlagArray('attr-gte', false, [
            $this->ruleFor(1, 'attribute', 'tier', 'gte', [['identifier' => '2']], true),
        ])]);

        $this->assertFalse($this->createManager()->withContext($this->parityC1())->single('attr-gte')->asBool()); // tier=1
    }

    public function testNumericGtReturnsTrueWhenAbove(): void
    {
        $this->cacheWith([$this->boolFlagArray('attr-gt', false, [
            $this->ruleFor(1, 'attribute', 'tier', 'gt', [['identifier' => '1']], true),
        ])]);

        $this->assertTrue($this->createManager()->withContext($this->parityC2())->single('attr-gt')->asBool()); // tier=3
    }

    public function testNumericGtReturnsFalseWhenEqual(): void
    {
        $this->cacheWith([$this->boolFlagArray('attr-gt', false, [
            $this->ruleFor(1, 'attribute', 'tier', 'gt', [['identifier' => '1']], true),
        ])]);

        $this->assertFalse($this->createManager()->withContext($this->parityC1())->single('attr-gt')->asBool()); // tier=1
    }

    public function testNumericLtReturnsTrueWhenBelow(): void
    {
        $this->cacheWith([$this->boolFlagArray('attr-lt', false, [
            $this->ruleFor(1, 'attribute', 'age', 'lt', [['identifier' => '30']], true),
        ])]);

        $this->assertTrue($this->createManager()->withContext($this->parityC1())->single('attr-lt')->asBool()); // age=25
    }

    public function testNumericLtReturnsFalseWhenAbove(): void
    {
        $this->cacheWith([$this->boolFlagArray('attr-lt', false, [
            $this->ruleFor(1, 'attribute', 'age', 'lt', [['identifier' => '30']], true),
        ])]);

        $this->assertFalse($this->createManager()->withContext($this->parityC2())->single('attr-lt')->asBool()); // age=42
    }

    public function testNumericLteReturnsTrueWhenAtThreshold(): void
    {
        $this->cacheWith([$this->boolFlagArray('attr-lte', false, [
            $this->ruleFor(1, 'attribute', 'age', 'lte', [['identifier' => '42']], true),
        ])]);

        $this->assertTrue($this->createManager()->withContext($this->parityC2())->single('attr-lte')->asBool()); // age=42
    }

    // =========================================================================
    // Rule ordering — first match wins
    // =========================================================================

    /**
     * C1 satisfies both country=US (rule 1) and plan=free (rule 2).
     * Rule evaluation is ordered by position, so rule 1 must win.
     */
    public function testFirstMatchingRuleWins(): void
    {
        $this->cacheWith([$this->stringFlagArray('first-match', 'fallback', [
            $this->ruleFor(1, 'attribute', 'country', 'equal', [['identifier' => 'US']], 'us-first', 'string'),
            $this->ruleFor(2, 'attribute', 'plan', 'equal', [['identifier' => 'free']], 'free-second', 'string'),
        ])]);

        $this->assertSame('us-first', $this->createManager()->withContext($this->parityC1())->single('first-match')->asString());
    }

    /**
     * C1 (country=US) → rule 1 → treatment-us.
     * C2 (plan=pro, country=CA) → rule 2 → treatment-pro.
     */
    public function testVariantFlagSelectsRuleByContext(): void
    {
        $this->cacheWith([$this->stringFlagArray('variant', 'control', [
            $this->ruleFor(1, 'attribute', 'country', 'equal', [['identifier' => 'US']], 'treatment-us', 'string'),
            $this->ruleFor(2, 'attribute', 'plan', 'equal', [['identifier' => 'pro']], 'treatment-pro', 'string'),
        ])]);

        $manager = $this->createManager();

        $this->assertSame('treatment-us', $manager->withContext($this->parityC1())->single('variant')->asString());
        $this->assertSame('treatment-pro', $manager->withContext($this->parityC2())->single('variant')->asString());
    }

    // =========================================================================
    // Rollout — cross-SDK vectors (abc123 salt, 20%)
    // =========================================================================

    /** abc123 + ctx-beta → bucket 3 < 20 → in rollout → true. */
    public function testRollout20PercentIncludesContextInBucket(): void
    {
        $this->cacheWith([$this->boolFlagArray('r20', false, [], $this->parityRollout(20, self::P_SALT))]);

        $this->assertTrue($this->createManager()->withContext($this->parityC3())->single('r20')->asBool());
    }

    /** abc123 + ctx-alpha → bucket 54 >= 20 → not in rollout → false. */
    public function testRollout20PercentExcludesContextOutsideBucket(): void
    {
        $this->cacheWith([$this->boolFlagArray('r20', false, [], $this->parityRollout(20, self::P_SALT))]);

        $this->assertFalse($this->createManager()->withContext($this->parityC4())->single('r20')->asBool());
    }

    // =========================================================================
    // Rollout — gate rules (rollout requires additional criteria)
    // =========================================================================

    /** C3 passes country=US gate AND is in 20% bucket → rollout value true. */
    public function testGatedRolloutIncludesContextPassingGate(): void
    {
        $gate = $this->ruleFor(1, 'attribute', 'country', 'equal', [['identifier' => 'US']], true);
        $this->cacheWith([$this->boolFlagArray('r20-us', false, [], $this->parityRollout(20, self::P_SALT, [$gate]))]);

        $this->assertTrue($this->createManager()->withContext($this->parityC3())->single('r20-us')->asBool());
    }

    /** C2 (country=CA) fails country=US gate → base value false, regardless of bucket. */
    public function testGatedRolloutExcludesContextFailingGate(): void
    {
        $gate = $this->ruleFor(1, 'attribute', 'country', 'equal', [['identifier' => 'US']], true);
        $this->cacheWith([$this->boolFlagArray('r20-us', false, [], $this->parityRollout(20, self::P_SALT, [$gate]))]);

        $this->assertFalse($this->createManager()->withContext($this->parityC2())->single('r20-us')->asBool());
    }

    /** 1% rollout with a gate (country=ZZ) that never matches — effectively off. */
    public function testEffectivelyZeroRolloutViaNeverMatchingGate(): void
    {
        $gate = $this->ruleFor(1, 'attribute', 'country', 'equal', [['identifier' => 'ZZ']], true);
        $this->cacheWith([$this->boolFlagArray('r1-zz', false, [], $this->parityRollout(1, self::P_SALT, [$gate]))]);

        $this->assertFalse($this->createManager()->withContext($this->parityC1())->single('r1-zz')->asBool());
    }

    // =========================================================================
    // Cache — refreshRules flips evaluated value
    // =========================================================================

    public function testRefreshRulesFlipsEvaluatedValue(): void
    {
        $stalePayload = json_encode([
            'version' => 'v1',
            'flags' => [$this->boolFlagArray('cache-flip', false)],
        ]);
        $freshResponse = RulesResponse::fromArray([
            'version' => 'v1',
            'flags' => [$this->boolFlagArray('cache-flip', true)],
        ]);

        /** @var \Mockery\Expectation $cacheGet2 */
        $cacheGet2 = $this->cache->shouldReceive('get');
        $cacheGet2->with('zenmanage_rules')->once()->andReturn($stalePayload);
        /** @var \Mockery\Expectation $apiGetRules */
        $apiGetRules = $this->apiClient->shouldReceive('getRules');
        $apiGetRules->once()->andReturn($freshResponse);
        /** @var \Mockery\Expectation $cacheSet */
        $cacheSet = $this->cache->shouldReceive('set');
        $cacheSet->once();

        $manager = $this->createManager();

        $this->assertFalse($manager->single('cache-flip')->asBool(), 'Initial: stale cache value');
        $this->assertFalse($manager->single('cache-flip')->asBool(), 'Before refresh: still stale in-memory');

        $manager->refreshRules();

        $this->assertTrue($manager->single('cache-flip')->asBool(), 'After refresh: new API value');
    }

    // =========================================================================
    // Negated operators through full evaluation pipeline
    // =========================================================================

    /** notequal free: C1 plan=free — rule does NOT fire — base false. */
    public function testNegatedAttributeEqualsReturnsFalseWhenMatches(): void
    {
        $this->cacheWith([$this->boolFlagArray('notequal', false, [
            $this->ruleFor(1, 'attribute', 'plan', 'notequal', [['identifier' => 'free']], true),
        ])]);

        $this->assertFalse($this->createManager()->withContext($this->parityC1())->single('notequal')->asBool()); // plan=free
    }

    /** notequal free: C2 plan=pro — rule fires — true. */
    public function testNegatedAttributeEqualsReturnsTrueWhenNotMatches(): void
    {
        $this->cacheWith([$this->boolFlagArray('notequal', false, [
            $this->ruleFor(1, 'attribute', 'plan', 'notequal', [['identifier' => 'free']], true),
        ])]);

        $this->assertTrue($this->createManager()->withContext($this->parityC2())->single('notequal')->asBool()); // plan=pro
    }

    /** notcontains alpha: C1 tags=[alpha,beta] — alpha present — rule does NOT fire — false. */
    public function testAttributeNotContainsReturnsFalseWhenPresent(): void
    {
        $this->cacheWith([$this->boolFlagArray('notcontains', false, [
            $this->ruleFor(1, 'attribute', 'tags', 'notcontains', [['identifier' => 'alpha']], true),
        ])]);

        $this->assertFalse($this->createManager()->withContext($this->parityC1())->single('notcontains')->asBool());
    }

    /** notcontains alpha: C2 tags=[beta,gamma] — alpha absent — rule fires — true. */
    public function testAttributeNotContainsReturnsTrueWhenAbsent(): void
    {
        $this->cacheWith([$this->boolFlagArray('notcontains', false, [
            $this->ruleFor(1, 'attribute', 'tags', 'notcontains', [['identifier' => 'alpha']], true),
        ])]);

        $this->assertTrue($this->createManager()->withContext($this->parityC2())->single('notcontains')->asBool());
    }

    /** not_in [free,trial]: C1 plan=free IS in list — rule does NOT fire — false. */
    public function testAttributeNotInReturnsFalseWhenInList(): void
    {
        $this->cacheWith([$this->boolFlagArray('not-in', false, [
            $this->ruleFor(1, 'attribute', 'plan', 'not_in', [['identifier' => 'free'], ['identifier' => 'trial']], true),
        ])]);

        $this->assertFalse($this->createManager()->withContext($this->parityC1())->single('not-in')->asBool()); // plan=free
    }

    /** not_in [free,trial]: C2 plan=pro NOT in list — rule fires — true. */
    public function testAttributeNotInReturnsTrueWhenNotInList(): void
    {
        $this->cacheWith([$this->boolFlagArray('not-in', false, [
            $this->ruleFor(1, 'attribute', 'plan', 'not_in', [['identifier' => 'free'], ['identifier' => 'trial']], true),
        ])]);

        $this->assertTrue($this->createManager()->withContext($this->parityC2())->single('not-in')->asBool()); // plan=pro
    }

    /** starts_with admin: C1 email=alice@acme.com — prefix absent — false. */
    public function testAttributeStartsWithReturnsFalseWhenPrefixAbsent(): void
    {
        $this->cacheWith([$this->boolFlagArray('starts-with', false, [
            $this->ruleFor(1, 'attribute', 'email', 'starts_with', [['identifier' => 'admin']], true),
        ])]);

        $this->assertFalse($this->createManager()->withContext($this->parityC1())->single('starts-with')->asBool());
    }

    // =========================================================================
    // Negated segment (context) operator
    // =========================================================================

    /** notequal user-ca-pro: C1 identifier=user-us-free — different — rule fires — true. */
    public function testSegmentNotEqualsReturnsTrueWhenDifferentIdentifier(): void
    {
        $this->cacheWith([$this->boolFlagArray('seg-neq', false, [
            $this->ruleFor(1, 'segment', null, 'notequal', [['identifier' => 'user-ca-pro', 'type' => 'user']], true),
        ])]);

        $this->assertTrue($this->createManager()->withContext($this->parityC1())->single('seg-neq')->asBool());
    }

    /** notequal user-ca-pro: C2 identifier=user-ca-pro — same — rule does NOT fire — false. */
    public function testSegmentNotEqualsReturnsFalseWhenSameIdentifier(): void
    {
        $this->cacheWith([$this->boolFlagArray('seg-neq', false, [
            $this->ruleFor(1, 'segment', null, 'notequal', [['identifier' => 'user-ca-pro', 'type' => 'user']], true),
        ])]);

        $this->assertFalse($this->createManager()->withContext($this->parityC2())->single('seg-neq')->asBool());
    }
}
