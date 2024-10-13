<?php

namespace Zenmanage;

class Config
{
    const DEFAULT_CONFIGURATION = [
        'api_endpoint' => 'https://api.zenmanage.com/',
        'environment_token' => '',
        'guzzle' => []
    ];

    protected $config;

    public function __construct($configFromConstructor = [])
    {
        if ($configFromConstructor == null) {
            $configFromConstructor = [];
        }

        if ($configFromConstructor instanceof self) {
            $configFromConstructor = $configFromConstructor->config;
        }

        $this->config = array_merge(
            $this->defaultConfig(),
            $configFromConstructor,
            $this->configFromEnvironment()
        );
    }

    public function get($key)
    {
        if (!array_key_exists($key, $this->config))
        {
            return null;
        }
        
        return $this->config[$key];
    }

    public function set($key, $value)
    {
        $this->config[$key] = $value;
    }

    private function defaultConfig()
    {
        return self::DEFAULT_CONFIGURATION;
    }

    private function configFromEnvironment()
    {
        $config = [];
        $keys = array_keys($this->defaultConfig());

        foreach ($keys as $key) {
            $value = getenv("ZENMANAGE_" . strtoupper($key), true);

            if ($value) {
                $config[$key] = $value;
            }
        }

        return $config;
    }
}
