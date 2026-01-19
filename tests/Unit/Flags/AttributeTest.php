<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Flags;

use PHPUnit\Framework\TestCase;
use Zenmanage\Flags\Context\Attribute;

final class AttributeTest extends TestCase
{
    public function testMatchesByKeyAndOptionalValue(): void
    {
        $attribute = new Attribute('plan', ['pro', 'enterprise']);

        $this->assertTrue($attribute->matches('plan'));
        $this->assertTrue($attribute->matches('plan', 'pro'));
        $this->assertFalse($attribute->matches('plan', 'free'));
        $this->assertFalse($attribute->matches('other'));
    }

    public function testJsonSerializeAndToString(): void
    {
        $attribute = new Attribute('region', ['us-east', 'eu-west']);

        $this->assertSame('region', $attribute->getKey());
        $this->assertSame(['us-east', 'eu-west'], $attribute->getValues());
        $this->assertSame(
            [
                'key' => 'region',
                'values' => [
                    ['value' => 'us-east'],
                    ['value' => 'eu-west'],
                ],
            ],
            $attribute->jsonSerialize(),
        );
        $this->assertSame('region:us-east,eu-west', (string) $attribute);
    }
}
