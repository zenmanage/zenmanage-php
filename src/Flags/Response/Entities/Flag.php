<?php

namespace Zenmanage\Flags\Response\Entities;

use \Zenmanage\Exceptions\MalformedResponseException;

class Flag
{
    public static function map(string|null $json) : Flag
    {
        if ($json == null)
        {
            throw new MalformedResponseException();
        }

        $source = json_decode($json)->data->flag;
        return new Flag($source->key, $source->name, $source->type, $source->value);
    }

    public string $key;
    public string $name;
    public string $type;
    private Object $value;

    public function __construct(string $key, string $name, string $type, Object $value) {
        $this->key = $key;
        $this->name = $name;
        $this->type = $type;
        $this->value = $value;
    }

    public function getValue() : bool|float|int|string {
        switch($this->type) {
            case 'boolean':
                return $this->getBoolean();
            case 'number':
                return $this->getNumber();
            case 'string':
            default:
                return $this->getString();
        }
    }

    private function getBoolean() : bool {
        return (bool)($this->findPropertyByNameCaseInsensitive($this->value, 'boolean'));
    }

    private function getNumber() : float|int {
        $value = $this->findPropertyByNameCaseInsensitive($this->value, 'number');
        return is_int($value) ? (int)$value : (float)$value;
    }

    private function getString() : ?string {
        return $this->findPropertyByNameCaseInsensitive($this->value, 'string');
    }

    private function findPropertyByNameCaseInsensitive($obj, $name)
    {
        $propertyNames = array_keys(get_object_vars($obj));
        foreach($propertyNames as $propertyName)
        {
            if (strcasecmp($name, $propertyName) == 0) {
                return $obj->$propertyName;
            }
        }
        return null;
    }
}
