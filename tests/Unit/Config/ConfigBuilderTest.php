<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Zenmanage\Config\Config;
use Zenmanage\Config\ConfigBuilder;
use Zenmanage\Exception\ConfigurationException;

final class ConfigBuilderTest extends TestCase
{
    public function testCreateWithDefaults(): void
    {
        $config = ConfigBuilder::create()
            ->withEnvironmentToken('tok_test')
            ->build();

        $this->assertSame('tok_test', $config->getEnvironmentToken());
        $this->assertSame(3600, $config->getCacheTtl());
        $this->assertSame('memory', $config->getCacheBackend());
        $this->assertFalse($config->isUsageReportingEnabled());
    }

    public function testCreateWithCustomValues(): void
    {
        $config = ConfigBuilder::create()
            ->withEnvironmentToken('tok_test')
            ->withCacheTtl(7200)
            ->withCacheBackend('filesystem')
            ->withCacheDirectory('/tmp/test')
            ->enableUsageReporting()
            ->withApiEndpoint('https://custom.api.com')
            ->build();

        $this->assertSame('tok_test', $config->getEnvironmentToken());
        $this->assertSame(7200, $config->getCacheTtl());
        $this->assertSame('filesystem', $config->getCacheBackend());
        $this->assertSame('/tmp/test', $config->getCacheDirectory());
        $this->assertTrue($config->isUsageReportingEnabled());
        $this->assertSame('https://custom.api.com', $config->getApiEndpoint());
    }

    public function testMissingEnvironmentToken(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Environment token is required');

        ConfigBuilder::create()->build();
    }

    public function testInvalidCacheBackend(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid cache backend');

        ConfigBuilder::create()
            ->withEnvironmentToken('tok_test')
            ->withCacheBackend('invalid')
            ->build();
    }

    public function testFilesystemCacheRequiresDirectory(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Cache directory is required');

        ConfigBuilder::create()
            ->withEnvironmentToken('tok_test')
            ->withCacheBackend('filesystem')
            ->build();
    }

    public function testDisableUsageReporting(): void
    {
        $config = ConfigBuilder::create()
            ->withEnvironmentToken('tok_test')
            ->enableUsageReporting()
            ->disableUsageReporting()
            ->build();

        $this->assertFalse($config->isUsageReportingEnabled());
    }
}
