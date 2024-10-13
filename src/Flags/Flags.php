<?php
namespace Zenmanage\Flags;

use Exception;
use \Zenmanage\Exceptions\InvalidTokenException;
use \Zenmanage\Exceptions\NoResponseException;
use \Zenmanage\Exceptions\FlagNotFoundException;
use \Zenmanage\Flags\Request\Entities\DefaultValue;
use \Zenmanage\Flags\Request\Entities\Context\Context;
use \Zenmanage\Flags\Response\Entities\Flag;

class Flags {
    private $client;

    public function __construct($client) {
        $this->client = $client;
    }

    public function all(Context $context = null, array $defaultValues = null) : array {
        $endpoint = '/flags';

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
            return array_map(fn($value): Flag => new Flag($value['flag']['key'], '', $value['flag']['type'], (object)$value['flag']['value']), $results['data']);
        } catch (InvalidTokenException $e) {
            throw $e;
        } catch (Exception $e) {
            error_log($e->getMessage());
            
            if ($defaultValues != null) {
                return array_map(fn($value): Flag => $value->toFlag(), $defaultValues);
            }
            
            throw new NoResponseException();
        }
    }

    public function report(string $key) {

        $endpoint = "/flags/$key/usage";

        try {
            $this->client->post($endpoint);
        } catch (InvalidTokenException|FlagNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            error_log($e->getMessage());
        }

    }

    public function single(string $key, Context $context = null, ?string $type = null, string|bool|int|float $defaultValue = null) : Flag {
        
        $default = null;
        if ($defaultValue != null) {
            $default = new DefaultValue($key, $type, $defaultValue);
        }

        $contexts = null;
        if ($context != null) {
            $contexts = json_encode($context);
        }

        $headers = [
            'X-DEFAULT-VALUE' => $default != null ? $default->toJson() : null,
            'X-ZENMANAGE-CONTEXT' => $contexts,
        ];

        $endpoint = "/flags/$key";

        try {
            return Flag::map($this->client->get($endpoint, $headers));
        } catch (InvalidTokenException|FlagNotFoundException $e) {
            throw $e;
        } catch (Exception) {
            if ($defaultValue != null) {
                return $default->toFlag();
            }

            throw new NoResponseException();
        }
    }
}
