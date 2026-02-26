<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Flags;

use PHPUnit\Framework\TestCase;
use Zenmanage\Flags\Rollout;
use Zenmanage\Flags\Target;
use Zenmanage\Rules\Rule;

final class RolloutTest extends TestCase
{
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
            'percentage' => 25,
            'salt' => 'abc123def456',
            'status' => 'active',
        ], $overrides);
    }

    public function testFromArrayCreatesRollout(): void
    {
        $rollout = Rollout::fromArray($this->rolloutArray());

        $this->assertInstanceOf(Rollout::class, $rollout);
        $this->assertSame(25, $rollout->getPercentage());
        $this->assertSame('abc123def456', $rollout->getSalt());
        $this->assertSame('active', $rollout->getStatus());
        $this->assertInstanceOf(Target::class, $rollout->getTarget());
        $this->assertSame('tar_rollout', $rollout->getTarget()->getVersion());
        $this->assertIsArray($rollout->getRules());
        $this->assertCount(0, $rollout->getRules());
    }

    public function testFromArrayWithRules(): void
    {
        $data = $this->rolloutArray([
            'rules' => [
                [
                    'version' => 'rule-v1',
                    'description' => 'Rollout rule',
                    'criteria' => [
                        'selector' => 'segment',
                        'selector_subtype' => null,
                        'comparer' => 'equal',
                        'values' => [],
                    ],
                    'position' => 1,
                    'value' => [
                        'version' => 'val-rule',
                        'value' => ['boolean' => false],
                    ],
                ],
            ],
        ]);

        $rollout = Rollout::fromArray($data);

        $this->assertCount(1, $rollout->getRules());
        $this->assertInstanceOf(Rule::class, $rollout->getRules()[0]);
    }

    public function testFromArrayWithMissingFieldsUsesDefaults(): void
    {
        $rollout = Rollout::fromArray([]);

        $this->assertSame(0, $rollout->getPercentage());
        $this->assertSame('', $rollout->getSalt());
        $this->assertSame('active', $rollout->getStatus());
        $this->assertCount(0, $rollout->getRules());
    }

    public function testJsonSerialize(): void
    {
        $rollout = Rollout::fromArray($this->rolloutArray());
        $json = $rollout->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertSame(25, $json['percentage']);
        $this->assertSame('abc123def456', $json['salt']);
        $this->assertSame('active', $json['status']);
        $this->assertIsArray($json['target']);
        $this->assertSame('tar_rollout', $json['target']['version']);
        $this->assertIsArray($json['rules']);
        $this->assertCount(0, $json['rules']);
    }

    public function testJsonSerializeRoundTrip(): void
    {
        $original = Rollout::fromArray($this->rolloutArray());
        $serialized = $original->jsonSerialize();
        $restored = Rollout::fromArray($serialized);

        $this->assertSame($original->getPercentage(), $restored->getPercentage());
        $this->assertSame($original->getSalt(), $restored->getSalt());
        $this->assertSame($original->getStatus(), $restored->getStatus());
        $this->assertSame(
            $original->getTarget()->getVersion(),
            $restored->getTarget()->getVersion(),
        );
    }
}
