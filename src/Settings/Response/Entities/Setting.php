<?php

namespace Zenmanage\Settings\Response\Entities;

use \Zenmanage\Exceptions\MalformedResponseException;

class Setting
{
    static public function map(string|null $json) : Setting
    {
        if ($json == null)
        {
            throw new MalformedResponseException();
        }

        $source = json_decode($json)->data->setting;
        return new Setting($source->key, $source->name, $source->type, $source->value);
    }

    public string $key;
    public string $name;
    public string $type;
    private Object $value;

    function __construct(string $key, string $name, string $type, Object $value) {
        $this->key = $key;
        $this->name = $name;
        $this->type = $type;
        $this->value = $value;
    }

    function getValue() : bool|float|int|string {
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
        return (bool)($this->value->boolean);
    }

    private function getNumber() : float|int {
        $value = $this->value->number;
        return is_int($value) ? (int)$value : (float)$value;
    }

    private function getString() : string {
        return $this->value->string;
    }
}