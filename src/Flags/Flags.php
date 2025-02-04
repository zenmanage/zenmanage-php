<?php
namespace Zenmanage\Flags;

use Exception;
use \Zenmanage\Exceptions\InvalidTokenException;
use \Zenmanage\Exceptions\NoResponseException;
use \Zenmanage\Exceptions\FlagNotFoundException;
use \Zenmanage\Flags\Request\Entities\DefaultValue;
use \Zenmanage\Flags\Request\Entities\Context\Context;
use \Zenmanage\Flags\Response\Entities\Flag;
use \Zenmanage\Shared\HttpClient;

class Flags {

    private HttpClient $client;
    private ?Context $context;
    private ?array $defaults = [];

    public function __construct($client) {
        $this->client = $client;
    }

    public function all() : array {
        $endpoint = '/flags';

        $defaultValues = null;
        if ($this->defaults != null) {
            $defaultValues = json_encode(array_map(fn($value): array => $value->toArray(), $this->defaults));
        }

        $headers = [
            'X-DEFAULT-VALUE' => $defaultValues,
            'X-ZENMANAGE-CONTEXT' => $this->context != null ? json_encode($this->context) : null,
        ];

        try {
            $results = json_decode($this->client->get($endpoint, $headers), true);
            return array_map(fn($value): Flag => new Flag($value['flag']['key'], '', $value['flag']['type'], (object)$value['flag']['value']), $results['data']);
        } catch (InvalidTokenException $e) {
            throw $e;
        } catch (Exception $e) {
            error_log($e->getMessage());
            
            if ($this->defaults != null) {
                return array_map(fn($value): Flag => $value->toFlag(), $this->defaults);
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

    public function single(string $key, ?string $type = null, string|bool|int|float $value = null) : Flag {
        
        $defaultValue = null;

        if ($this->defaults != null) {
            $default = array_filter($this->defaults, fn($value): bool => $value->key == $key);
            if (count($default) == 1) {
                $defaultValue = reset($default);
            }
        }

        if ($value != null) {
            $defaultValue = new DefaultValue($key, $type, $value);
        }

        $headers = [
            'X-DEFAULT-VALUE' => $defaultValue != null ? $defaultValue->toJson() : null,
            'X-ZENMANAGE-CONTEXT' => $this->context != null ? json_encode($this->context) : null,
        ];

        $endpoint = "/flags/$key";

        try {
            return Flag::map($this->client->get($endpoint, $headers));
        } catch (InvalidTokenException|FlagNotFoundException $e) {
            throw $e;
        } catch (Exception) {
            if ($defaultValue != null) {
                return $defaultValue->toFlag();
            }

            throw new NoResponseException();
        }
    }

    public function withContext(Context $context): Flags
    {
        $this->context = $context;
        return $this;
    }

    public function withDefault(string $key, string $type, string|bool|float|int $defaultValue): Flags
    {
        array_push($this->defaults, new DefaultValue($key, $type, $defaultValue));
        return $this;
    }
}
