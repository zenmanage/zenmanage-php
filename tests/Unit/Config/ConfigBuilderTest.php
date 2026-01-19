<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Zenmanage\Config\ConfigBuilder;
use Zenmanage\Exception\ConfigurationException;

final class ConfigBuilderTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        $this->envBackup = [
            'ZENMANAGE_ENVIRONMENT_TOKEN' => getenv('ZENMANAGE_ENVIRONMENT_TOKEN'),
            'ZENMANAGE_CACHE_TTL' => getenv('ZENMANAGE_CACHE_TTL'),
            'ZENMANAGE_CACHE_BACKEND' => getenv('ZENMANAGE_CACHE_BACKEND'),
            'ZENMANAGE_CACHE_DIR' => getenv('ZENMANAGE_CACHE_DIR'),
            'ZENMANAGE_ENABLE_USAGE_REPORTING' => getenv('ZENMANAGE_ENABLE_USAGE_REPORTING'),
            'ZENMANAGE_API_ENDPOINT' => getenv('ZENMANAGE_API_ENDPOINT'),
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv($key . '=' . $value);
            }
        }
    }

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

    public function testFromEnvironmentAppliesAllVariables(): void
    {
        putenv('ZENMANAGE_ENVIRONMENT_TOKEN=tok_env');
        putenv('ZENMANAGE_CACHE_TTL=1800');
        putenv('ZENMANAGE_CACHE_BACKEND=filesystem');
        putenv('ZENMANAGE_CACHE_DIR=/tmp/cache-dir');
        putenv('ZENMANAGE_ENABLE_USAGE_REPORTING=true');
        putenv('ZENMANAGE_API_ENDPOINT=https://env.example.com');

        $config = ConfigBuilder::fromEnvironment()->build();

        $this->assertSame('tok_env', $config->getEnvironmentToken());
        $this->assertSame(1800, $config->getCacheTtl());
        $this->assertSame('filesystem', $config->getCacheBackend());
        $this->assertSame('/tmp/cache-dir', $config->getCacheDirectory());
        $this->assertTrue($config->isUsageReportingEnabled());
        $this->assertSame('https://env.example.com', $config->getApiEndpoint());
    }

    public function testFromEnvironmentFallsBackToDefaults(): void
    {
        putenv('ZENMANAGE_ENVIRONMENT_TOKEN=tok_env_default');
        putenv('ZENMANAGE_CACHE_TTL');
        putenv('ZENMANAGE_CACHE_BACKEND');
        putenv('ZENMANAGE_CACHE_DIR');
        putenv('ZENMANAGE_ENABLE_USAGE_REPORTING');
        putenv('ZENMANAGE_API_ENDPOINT');

        $config = ConfigBuilder::fromEnvironment()->build();

        $this->assertSame('tok_env_default', $config->getEnvironmentToken());
        $this->assertSame(3600, $config->getCacheTtl());
        $this->assertSame('memory', $config->getCacheBackend());
        $this->assertNull($config->getCacheDirectory());
        $this->assertFalse($config->isUsageReportingEnabled());
        $this->assertSame('https://api.zenmanage.com', $config->getApiEndpoint());
    }
}
