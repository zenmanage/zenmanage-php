<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use Zenmanage\Cache\InMemoryCache;

final class InMemoryCacheTest extends TestCase
{
    private InMemoryCache $cache;

    protected function setUp(): void
    {
        $this->cache = new InMemoryCache();
    }

    public function testSetAndGet(): void
    {
        $this->cache->set('test-key', 'test-value');

        $this->assertSame('test-value', $this->cache->get('test-key'));
    }

    public function testGetNonExistentKey(): void
    {
        $this->assertNull($this->cache->get('non-existent'));
    }

    public function testHas(): void
    {
        $this->cache->set('test-key', 'test-value');

        $this->assertTrue($this->cache->has('test-key'));
        $this->assertFalse($this->cache->has('non-existent'));
    }

    public function testDelete(): void
    {
        $this->cache->set('test-key', 'test-value');
        $this->cache->delete('test-key');

        $this->assertNull($this->cache->get('test-key'));
    }

    public function testClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->clear();

        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
    }

    public function testTtlExpiration(): void
    {
        $this->cache->set('test-key', 'test-value', 1);

        // Should exist immediately
        $this->assertSame('test-value', $this->cache->get('test-key'));

        // Wait for expiration
        sleep(2);

        // Should be expired
        $this->assertNull($this->cache->get('test-key'));
    }

    public function testNoTtl(): void
    {
        $this->cache->set('test-key', 'test-value', null);

        // Should exist
        $this->assertSame('test-value', $this->cache->get('test-key'));

        // Wait a bit
        sleep(1);

        // Should still exist
        $this->assertSame('test-value', $this->cache->get('test-key'));
    }
}
