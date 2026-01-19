<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Zenmanage\Rules\Condition;
use Zenmanage\Rules\ConditionValue;

final class ConditionTest extends TestCase
{
    public function testFromArrayParsesStringAndObjectValues(): void
    {
        $condition = Condition::fromArray([
            'selector' => 'attribute',
            'selector_subtype' => 'plan',
            'comparer' => 'equal',
            'values' => [
                ['identifier' => 'enterprise', 'type' => ''],
                'pro',
            ],
        ]);

        $this->assertSame('attribute', $condition->getSelector());
        $this->assertSame('plan', $condition->getSelectorSubtype());
        $this->assertSame('equal', $condition->getComparer());
        $this->assertCount(2, $condition->getValues());
        $this->assertInstanceOf(ConditionValue::class, $condition->getValues()[1]);
    }

    public function testJsonSerialize(): void
    {
        $condition = new Condition(
            selector: 'segment',
            selectorSubtype: null,
            comparer: 'equal',
            values: [new ConditionValue('user-1', 'user')],
        );

        $this->assertSame(
            [
                'selector' => 'segment',
                'selector_subtype' => null,
                'comparer' => 'equal',
                'values' => [
                    ['identifier' => 'user-1', 'type' => 'user'],
                ],
            ],
            $condition->jsonSerialize(),
        );
    }
}
