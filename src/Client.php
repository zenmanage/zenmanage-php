<?php
namespace Zenmanage;

use \Zenmanage\Settings\Settings;
use \Zenmanage\Settings\Request\Entities\Context\Context;
use \Zenmanage\Shared\HttpClient;

class Client
{
    public $settings;

    public function __construct($config = null)
    {
        $config = new Config($config);
        $client = new HttpClient($config);

        $this->settings = new Settings($client);
    }

    public function all(Context $context = null, array $defaults = null)
    {
        return $this->settings->all($context, $defaults);
    }

    public function setting(Context $context = null, string $key, string $type, string|bool|float|int $defaultValue) : \Zenmanage\Settings\Response\Entities\Setting
    {
        return $this->settings->single($context, $key, $type, $defaultValue);
    }
}