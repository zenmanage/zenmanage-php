<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Flags;

use PHPUnit\Framework\TestCase;
use Zenmanage\Flags\Flag;
use Zenmanage\Flags\Target;
use Zenmanage\Rules\Rule;
use Zenmanage\Rules\RuleValue;

final class FlagTest extends TestCase
{
    private function makeFlag(mixed $value, string $type = 'boolean'): Flag
    {
        $target = new Target('t1', null, null, null, new RuleValue('rv', $value));

        return new Flag('f1', $type, 'flag-key', 'Flag Name', $target, []);
    }

    public function testIsEnabledOnlyForBooleanType(): void
    {
        $enabledFlag = $this->makeFlag(['boolean' => true], 'boolean');
        $disabledFlag = $this->makeFlag(['boolean' => false], 'boolean');
        $nonBooleanFlag = $this->makeFlag(['boolean' => true], 'string');

        $this->assertTrue($enabledFlag->isEnabled());
        $this->assertFalse($disabledFlag->isEnabled());
        $this->assertFalse($nonBooleanFlag->isEnabled());
    }

    public function testCastingHelpers(): void
    {
        $stringFlag = $this->makeFlag(['string' => 'hello'], 'string');
        $numericStringFlag = $this->makeFlag('99', 'number');
        $numberFlag = $this->makeFlag(['number' => 1.5], 'number');
        $boolFlag = $this->makeFlag(['boolean' => true], 'boolean');
        $fallbackStringFlag = $this->makeFlag(['unknown' => []], 'string');
        $fallbackNumberFlag = $this->makeFlag(['unknown' => []], 'number');

        $this->assertSame('hello', $stringFlag->asString());
        $this->assertSame(99, $numericStringFlag->asNumber());
        $this->assertSame(1.5, $numberFlag->asNumber());
        $this->assertTrue($boolFlag->asBool());
        $this->assertSame('', $fallbackStringFlag->asString());
        $this->assertSame(0, $fallbackNumberFlag->asNumber());
    }

    public function testAsJsonReturnsTheStructuredValue(): void
    {
        $objectShapedFlag = $this->makeFlag(['json' => ['theme' => 'dark', 'limits' => [1, 2, 3]]], 'json');
        $arrayShapedFlag = $this->makeFlag(['json' => [1, 2, 3]], 'json');

        $this->assertSame(['theme' => 'dark', 'limits' => [1, 2, 3]], $objectShapedFlag->asJson());
        $this->assertSame([1, 2, 3], $arrayShapedFlag->asJson());
    }

    /**
     * Cross-type coercion semantics for json, documented in the README: a
     * non-json flag's asJson() and a json flag's asString()/asNumber() both
     * fall back to that accessor's normal "safe zero value" rather than
     * throwing or attempting a lossy conversion.
     */
    public function testJsonCoercionFallbacksMatchOtherTypes(): void
    {
        $jsonFlag = $this->makeFlag(['json' => ['a' => 1]], 'json');
        $stringFlag = $this->makeFlag(['string' => 'hello'], 'string');
        $scalarJsonFlag = $this->makeFlag(['json' => 'not-an-object-or-array'], 'json');

        $this->assertSame('', $jsonFlag->asString());
        $this->assertSame(0, $jsonFlag->asNumber());
        $this->assertSame([], $stringFlag->asJson());
        $this->assertSame([], $scalarJsonFlag->asJson());
    }

    public function testFromArrayAndJsonSerialize(): void
    {
        $data = [
            'version' => 'flag-v1',
            'type' => 'boolean',
            'key' => 'example-flag',
            'name' => 'Example Flag',
            'target' => [
                'version' => 'target-v1',
                'expired_at' => null,
                'published_at' => null,
                'scheduled_at' => null,
                'value' => [
                    'version' => 'value-v1',
                    'value' => ['boolean' => true],
                ],
            ],
            'rules' => [
                [
                    'version' => 'rule-v1',
                    'description' => 'First rule',
                    'criteria' => [
                        'selector' => 'segment',
                        'selector_subtype' => null,
                        'comparer' => 'equal',
                        'values' => [
                            ['identifier' => 'user-1', 'type' => 'user'],
                        ],
                    ],
                    'position' => 2,
                    'value' => [
                        'version' => 'rule-val',
                        'value' => ['boolean' => false],
                    ],
                ],
            ],
        ];

        $flag = Flag::fromArray($data);

        $this->assertSame('example-flag', $flag->getKey());
        $this->assertSame('Example Flag', $flag->getName());
        $this->assertCount(1, $flag->getRules());
        $this->assertInstanceOf(Rule::class, $flag->getRules()[0]);
        $this->assertSame(['boolean' => true], $flag->getValue());
        $this->assertSame('example-flag', (string) $flag);

        $serialized = $flag->jsonSerialize();
        $this->assertIsArray($serialized);
        $this->assertSame('flag-v1', $serialized['version']);
        $this->assertSame('boolean', $serialized['type']);
        $this->assertIsArray($serialized['rules']);
        $this->assertSame('rule-v1', $serialized['rules'][0]['version']);
    }
}
