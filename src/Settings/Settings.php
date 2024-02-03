<?php
namespace Zenmanage\Settings;

use Exception;
use \Zenmanage\Exceptions\InvalidTokenException;
use \Zenmanage\Exceptions\NoResponseException;
use \Zenmanage\Exceptions\SettingNotFoundException;
use \Zenmanage\Settings\Request\Entities\DefaultValue;
use \Zenmanage\Settings\Request\Entities\Context\Context;
use \Zenmanage\Settings\Response\Entities\Setting;

class Settings {
    private $client;

    public function __construct($client) {
        $this->client = $client;
    }

    public function all(Context $context = null, array $defaultValues = null) : array {
        $endpoint = '/settings';

        $defaults = null;
        if ($defaultValues != null) {
            $defaults = json_encode(array_map(fn($value): array => $value->toArray(), $defaultValues));
        }

        $contexts = null;
        if ($context != null) {
            $contexts = json_encode($context);
        }

        $headers = [
            'X-DEFAULT-VALUE' => $defaults,
            'X-ZENMANAGE-CONTEXT' => $contexts,
        ];

        try {
            $results = json_decode($this->client->get($endpoint, $headers), true);
            return array_map(fn($value): Setting => new Setting($value['setting']['key'], '', $value['setting']['type'], (object)$value['setting']['value']), $results['data']);
        } catch (InvalidTokenException $e) {
            throw $e;
        } catch (Exception $e) {
            report($e);
            
            if ($defaultValues != null) {
                return array_map(fn($value): Setting => new Setting($value->key, '', $value->type, (object)array($value->type => $value->value)), $defaultValues);
            }
            
            throw new NoResponseException();
        }
    }

    public function single(Context $context = null, string $key, string $type, string|bool|int|float $defaultValue = null) : Setting {
        
        $default = null;
        if ($defaultValue != null) {
            $default = new DefaultValue($key, $type, $defaultValue);
        }

        $contexts = null;
        if ($context != null) {
            $contexts = json_encode($context);
        }

        $headers = [
            'X-DEFAULT-VALUE' => $default->toJson(),
            'X-ZENMANAGE-CONTEXT' => $contexts,
        ];

        $endpoint = "/settings/$key";

        try {
            $result = Setting::map($this->client->get($endpoint, $headers));
            return $result;
        } catch (InvalidTokenException|SettingNotFoundException $e) {
            throw $e;
        } catch (Exception) {
            if ($defaultValue != null) {
                return new Setting($key, '', $type, (object)array($type => $defaultValue));
            }
            
            throw new NoResponseException();
        }
    }
}