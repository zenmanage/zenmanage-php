<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Zenmanage\Rules\ConditionValue;

final class ConditionValueTest extends TestCase
{
    public function testFromArrayAndJsonSerialize(): void
    {
        $value = ConditionValue::fromArray(['identifier' => 'user-1', 'type' => 'organization']);

        $this->assertSame('user-1', $value->getIdentifier());
        $this->assertSame('organization', $value->getType());
        $this->assertSame([
            'identifier' => 'user-1',
            'type' => 'organization',
        ], $value->jsonSerialize());
    }

    public function testDefaultsWhenFieldsMissing(): void
    {
        $value = ConditionValue::fromArray([]);

        $this->assertSame('', $value->getIdentifier());
        $this->assertNull($value->getType());
    }

    public function testTypeIsNullWhenExplicitlyNull(): void
    {
        $value = ConditionValue::fromArray(['identifier' => 'user-1', 'type' => null]);

        $this->assertSame('user-1', $value->getIdentifier());
        $this->assertNull($value->getType());
    }
}
