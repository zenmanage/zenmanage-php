<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Zenmanage\Rules\RuleValue;

final class RuleValueTest extends TestCase
{
    public function testFromArrayAndJsonSerialize(): void
    {
        $value = RuleValue::fromArray([
            'version' => 'rv1',
            'value' => ['boolean' => true],
        ]);

        $this->assertSame('rv1', $value->getVersion());
        $this->assertSame(['boolean' => true], $value->getValue());
        $this->assertSame([
            'version' => 'rv1',
            'value' => ['boolean' => true],
        ], $value->jsonSerialize());
    }

    public function testDefaultsWhenFieldsMissing(): void
    {
        $value = RuleValue::fromArray([]);

        $this->assertSame('', $value->getVersion());
        $this->assertNull($value->getValue());
    }
}
