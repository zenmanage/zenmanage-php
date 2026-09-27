<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Integration;

use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Zenmanage\Api\ApiClientInterface;
use Zenmanage\Api\Response\RulesResponse;
use Zenmanage\Cache\CacheInterface;
use Zenmanage\Flags\DefaultsCollection;
use Zenmanage\Flags\FlagManager;
use Zenmanage\Rules\RuleEngine;

/**
 * ZEN-1383: the `json` flag type (introduced by the API alongside
 * boolean/string/number) is now natively supported by this SDK — unlike the
 * still-hypothetical unknown type covered by UnknownFlagTypeTest, a json flag
 * is evaluated normally and exposed through asJson() as structured data.
 *
 * This test parses a full rules payload (through the real RulesResponse /
 * Flag parsing and the real RuleEngine, not mocks) to confirm end-to-end
 * behavior, not just that individual parsing methods don't throw.
 */
final class JsonFlagTypeTest extends TestCase
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
    private function rulesPayloadWithJsonFlag(): array
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
                    'version' => 'fla_json_001',
                    'type' => 'json',
                    'key' => 'feature-config',
                    'name' => 'Feature Config',
                    'target' => [
                        'version' => 'tar_json_001',
                        'expired_at' => null,
                        'published_at' => '2026-09-01T00:00:00+00:00',
                        'scheduled_at' => null,
                        'value' => [
                            'version' => 'val_json_001',
                            'value' => ['json' => ['nested' => ['a' => 1, 'b' => [2, 3]]]],
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

        $response = RulesResponse::fromArray($this->rulesPayloadWithJsonFlag());
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

    public function testJsonFlagEvaluatesToStructuredValueAlongsideOtherTypes(): void
    {
        $manager = $this->createManager();

        $this->assertTrue($manager->single('dark-mode')->asBool());

        $flag = $manager->single('feature-config');
        $this->assertSame('json', $flag->getType());
        $this->assertSame(['nested' => ['a' => 1, 'b' => [2, 3]]], $flag->asJson());
    }

    public function testAllIncludesTheJsonFlag(): void
    {
        $manager = $this->createManager();

        $flags = $manager->all();
        $byKey = [];
        foreach ($flags as $flag) {
            $byKey[$flag->getKey()] = $flag;
        }

        $this->assertArrayHasKey('feature-config', $byKey);
        $this->assertSame(['nested' => ['a' => 1, 'b' => [2, 3]]], $byKey['feature-config']->asJson());
    }

    public function testJsonFlagFallsBackToInlineArrayDefaultWhenNotFound(): void
    {
        $manager = $this->createManager();

        $flag = $manager->single('missing-config', ['fallback' => true]);

        $this->assertSame('json', $flag->getType());
        $this->assertSame(['fallback' => true], $flag->asJson());
    }

    public function testJsonFlagFallsBackToCollectionArrayDefaultWhenNotFound(): void
    {
        $defaults = DefaultsCollection::fromArray(['missing-config' => ['fallback' => true]]);
        $manager = $this->createManager($defaults);

        $flag = $manager->single('missing-config');

        $this->assertSame('json', $flag->getType());
        $this->assertSame(['fallback' => true], $flag->asJson());
    }
}
