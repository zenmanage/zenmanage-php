<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Flags;

use PHPUnit\Framework\TestCase;
use Zenmanage\Flags\Context\Attribute;
use Zenmanage\Flags\Context\Context;

final class ContextTest extends TestCase
{
    public function testAddAndRetrieveAttributes(): void
    {
        $context = new Context('user', 'Jane Doe', 'user-1', [new Attribute('plan', ['pro'])]);
        $context->addAttribute(new Attribute('role', ['admin']));

        $this->assertSame('user', $context->getType());
        $this->assertSame('Jane Doe', $context->getName());
        $this->assertSame('user-1', $context->getIdentifier());
        $this->assertSame('user-1', $context->getId());
        $this->assertTrue($context->hasAttribute('plan'));
        $this->assertTrue($context->hasAttribute('role'));
        $this->assertSame('plan', $context->getAttribute('plan')?->getKey());
        $this->assertCount(2, $context->getAttributes());
        $this->assertStringContainsString('user-1', (string) $context);
    }

    public function testFromArrayFiltersInvalidAttributes(): void
    {
        $context = Context::fromArray([
            'type' => 'organization',
            'name' => 'Acme Corp',
            'identifier' => 'acme-1',
            'attributes' => [
                ['key' => 'country', 'values' => [['value' => 'US'], ['value' => 'CA']]],
                ['values' => [['value' => 'missing-key']]],
                'not-an-attribute',
                ['key' => 'plan', 'values' => ['pro', 'growth']],
            ],
        ]);

        $this->assertSame('organization', $context->getType());
        $this->assertSame('Acme Corp', $context->getName());
        $this->assertSame('acme-1', $context->getIdentifier());
        $this->assertTrue($context->hasAttribute('country'));
        $this->assertTrue($context->hasAttribute('plan'));
        $this->assertCount(2, $context->getAttributes());

        $serialized = $context->jsonSerialize();
        $this->assertArrayHasKey('attributes', $serialized);
        $this->assertSame('country', $serialized['attributes'][0]['key']);
    }

    public function testSingleCreatesMinimalContext(): void
    {
        $context = Context::single('service', 'svc-123', 'Webhook Processor');

        $this->assertSame('service', $context->getType());
        $this->assertSame('svc-123', $context->getIdentifier());
        $this->assertSame([], $context->getAttributes());
    }
}
