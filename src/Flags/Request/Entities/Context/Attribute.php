<?php

namespace Zenmanage\Flags\Request\Entities\Context;

class Attribute
{
    public string $key;
    public array $values = [];

    public function __construct(string $key, array $values)
    {
        $this->key = $key;
        $this->values = $values;
    }
}
