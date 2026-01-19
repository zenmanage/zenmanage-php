<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Zenmanage\Rules\Condition;
use Zenmanage\Rules\ConditionValue;
use Zenmanage\Rules\Rule;
use Zenmanage\Rules\RuleValue;

final class RuleTest extends TestCase
{
    public function testFromArrayAndJsonSerialize(): void
    {
        $rule = Rule::fromArray([
            'version' => 'rule-v1',
            'description' => 'Test rule',
            'criteria' => [
                'selector' => 'segment',
                'selector_subtype' => null,
                'comparer' => 'equal',
                'values' => [
                    ['identifier' => 'user-1', 'type' => 'user'],
                ],
            ],
            'position' => 3,
            'value' => [
                'version' => 'val-1',
                'value' => ['boolean' => false],
            ],
        ]);

        $this->assertSame('rule-v1', $rule->getVersion());
        $this->assertSame('Test rule', $rule->getDescription());
        $this->assertSame(3, $rule->getPosition());
        $this->assertInstanceOf(Condition::class, $rule->getCriteria());
        $this->assertInstanceOf(RuleValue::class, $rule->getValue());

        $serialized = $rule->jsonSerialize();
        $this->assertSame('rule-v1', $serialized['version']);
        $this->assertSame('Test rule', $serialized['description']);
        $this->assertSame(3, $serialized['position']);
    }

    public function testDefaultsWhenFieldsMissing(): void
    {
        $rule = Rule::fromArray([
            'criteria' => [
                'selector' => 'attribute',
                'comparer' => 'equal',
                'values' => ['pro'],
            ],
            'value' => [
                'value' => ['boolean' => true],
            ],
        ]);

        $this->assertSame('', $rule->getVersion());
        $this->assertSame('', $rule->getDescription());
        $this->assertSame(0, $rule->getPosition());
        $this->assertInstanceOf(ConditionValue::class, $rule->getCriteria()->getValues()[0]);
    }
}
