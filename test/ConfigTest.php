<?php
namespace Zenmanage\Test;

use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function tearDown(): void
    {
        putenv("ZENMANAGE_API_ENDPOINT=");
        putenv("ZENMANAGE_ENVIRONMENT_TOKEN=");
    }

    public function testConfigFromArray()
    {
        $config = new \Zenmanage\Config(['key' => 'value']);
        $this->assertEquals('value', $config->get('key'));
    }

    public function testConfigFromNull()
    {
        $config = new \Zenmanage\Config(null);
        $this->assertEquals('https://api.zenmanage.com/', $config->get('api_endpoint'));
    }

    public function testConfigFromConfig()
    {
        $other = new \Zenmanage\Config(['key' => 'value']);
        $config = new \Zenmanage\Config($other);
        $this->assertEquals('value', $config->get('key'));
    }

    public function testAPIEndpointDefaultValue()
    {
        $config = new \Zenmanage\Config();
        $this->assertEquals('https://api.zenmanage.com/', $config->get('api_endpoint'));
    }

    public function testAPIEndpointFromEnvironment()
    {
        putenv("ZENMANAGE_API_ENDPOINT=http://example.com");

        $config = new \Zenmanage\Config();
        $this->assertEquals('http://example.com', $config->get('api_endpoint'));
    }

    public function testAPIEndpointFromConstructor()
    {
        $config = new \Zenmanage\Config(['api_endpoint' => 'http://localhost']);
        $this->assertEquals('http://localhost', $config->get('api_endpoint'));
    }

    public function testEnvironmentTokenDefaultValue()
    {
        $config = new \Zenmanage\Config();
        $this->assertEquals('', $config->get('environment_token'));
    }

    public function testEnvironmentTokenFromEnvironment()
    {
        putenv("ZENMANAGE_ENVIRONMENT_TOKEN=_environment");

        $config = new \Zenmanage\Config();
        $this->assertEquals('tok_environment', $config->get('environment_token'));
    }

    public function testEnvironmentTokenFromConstructor()
    {
        $config = new \Zenmanage\Config(['environment_token' => 'tok_constructor']);
        $this->assertEquals('tok_constructor', $config->get('environment_token'));
    }
}
