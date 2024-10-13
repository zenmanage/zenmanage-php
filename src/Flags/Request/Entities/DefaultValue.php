<?php

namespace Zenmanage\Flags\Request\Entities;

use \Zenmanage\Flags\Response\Entities\Flag;

class DefaultValue
{
    public string $key;
    public string $type;
    public string|bool|int|float $value;

    public function __construct(string $key, string $type, string|bool|int|float $value) {
        $this->key = $key;
        $this->type = $type;
        $this->value = $value;
    }

    public function toArray() : array {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'value' => $this->value,
        ];
    }

    public function toJson() : String {
        return json_encode($this->toArray());
    }

    public function toFlag() : Flag {
        return new Flag($this->key, '', $this->type, (object)array($this->type => $this->value));
    }
}
