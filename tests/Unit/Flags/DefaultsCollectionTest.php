<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Flags;

use PHPUnit\Framework\TestCase;
use Zenmanage\Flags\DefaultsCollection;

final class DefaultsCollectionTest extends TestCase
{
    public function testCreateEmpty(): void
    {
        $defaults = new DefaultsCollection();

        $this->assertSame([], $defaults->all());
    }

    public function testCreateWithDefaults(): void
    {
        $defaults = new DefaultsCollection([
            'flag1' => true,
            'flag2' => 'test',
            'flag3' => 42,
        ]);

        $this->assertTrue($defaults->has('flag1'));
        $this->assertTrue($defaults->has('flag2'));
        $this->assertTrue($defaults->has('flag3'));
        $this->assertFalse($defaults->has('flag4'));
    }

    public function testFromArray(): void
    {
        $defaults = DefaultsCollection::fromArray([
            'feature-x' => true,
            'feature-y' => false,
        ]);

        $this->assertTrue($defaults->get('feature-x'));
        $this->assertFalse($defaults->get('feature-y'));
    }

    public function testSet(): void
    {
        $defaults = new DefaultsCollection();
        $defaults->set('flag1', true);
        $defaults->set('flag2', 'test');

        $this->assertTrue($defaults->has('flag1'));
        $this->assertTrue($defaults->get('flag1'));
        $this->assertSame('test', $defaults->get('flag2'));
    }

    public function testFluentInterface(): void
    {
        $defaults = (new DefaultsCollection())
            ->set('flag1', true)
            ->set('flag2', 'test')
            ->set('flag3', 42);

        $this->assertCount(3, $defaults->all());
    }

    public function testGet(): void
    {
        $defaults = new DefaultsCollection([
            'flag1' => true,
            'flag2' => 'test',
        ]);

        $this->assertTrue($defaults->get('flag1'));
        $this->assertSame('test', $defaults->get('flag2'));
        $this->assertNull($defaults->get('nonexistent'));
    }

    public function testHas(): void
    {
        $defaults = new DefaultsCollection([
            'flag1' => true,
        ]);

        $this->assertTrue($defaults->has('flag1'));
        $this->assertFalse($defaults->has('flag2'));
    }

    public function testAll(): void
    {
        $data = [
            'flag1' => true,
            'flag2' => 'test',
            'flag3' => 42,
        ];

        $defaults = new DefaultsCollection($data);

        $this->assertSame($data, $defaults->all());
    }

    public function testJsonSerialize(): void
    {
        $data = [
            'flag1' => true,
            'flag2' => 'test',
        ];

        $defaults = new DefaultsCollection($data);

        $this->assertSame($data, $defaults->jsonSerialize());
    }
}
