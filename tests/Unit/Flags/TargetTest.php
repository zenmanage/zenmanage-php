<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Flags;

use PHPUnit\Framework\TestCase;
use Zenmanage\Flags\Target;
use Zenmanage\Rules\RuleValue;

final class TargetTest extends TestCase
{
    public function testFromArrayAndJsonSerialize(): void
    {
        $data = [
            'version' => 'target-v1',
            'expired_at' => '2026-02-01T00:00:00+00:00',
            'published_at' => '2026-01-01T00:00:00+00:00',
            'scheduled_at' => '2026-03-01T00:00:00+00:00',
            'value' => [
                'version' => 'value-v1',
                'value' => ['number' => 10],
            ],
        ];

        $target = Target::fromArray($data);

        $this->assertSame('target-v1', $target->getVersion());
        $this->assertSame('2026-02-01T00:00:00+00:00', $target->getExpiredAt());
        $this->assertSame('2026-01-01T00:00:00+00:00', $target->getPublishedAt());
        $this->assertSame('2026-03-01T00:00:00+00:00', $target->getScheduledAt());
        $this->assertSame(['number' => 10], $target->getValue()->getValue());

        $serialized = $target->jsonSerialize();
        $this->assertIsArray($serialized);
        $this->assertIsArray($serialized['value']);
        $this->assertSame('value-v1', $serialized['value']['version']);
        $this->assertSame(['number' => 10], $serialized['value']['value']);
    }

    public function testEmptyArrayDefaults(): void
    {
        $target = Target::fromArray([]);

        $this->assertSame('', $target->getVersion());
        $this->assertNull($target->getExpiredAt());
        $this->assertNull($target->getPublishedAt());
        $this->assertNull($target->getScheduledAt());
        $this->assertNull($target->getValue()->getValue());
    }

    public function testJsonSerializeWithDirectInstance(): void
    {
        $target = new Target('v1', null, null, null, new RuleValue('val', ['string' => 'demo']));

        $this->assertSame(
            [
                'version' => 'v1',
                'expired_at' => null,
                'published_at' => null,
                'scheduled_at' => null,
                'value' => ['version' => 'val', 'value' => ['string' => 'demo']],
            ],
            $target->jsonSerialize(),
        );
    }
}
