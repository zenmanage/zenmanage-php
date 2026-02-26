<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Flags;

use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Zenmanage\Api\ApiClientInterface;
use Zenmanage\Cache\CacheInterface;
use Zenmanage\Flags\Context\Attribute;
use Zenmanage\Flags\Context\Context;
use Zenmanage\Flags\Flag;
use Zenmanage\Flags\FlagManager;
use Zenmanage\Flags\Rollout;
use Zenmanage\Flags\Target;
use Zenmanage\Rules\RuleEngine;
use Zenmanage\Rules\RuleValue;

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
        $this->apiClient->shouldReceive('reportUsage')->byDefault();

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

        $this->cache->shouldReceive('get')
            ->with('zenmanage_rules')
            ->andReturn($payload);
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

    /**
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
        $this->assertSame(50, $json['rollout']['percentage']);
        $this->assertSame('test-salt', $json['rollout']['salt']);
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
}
