<?php

namespace Zenmanage\Settings\Request\Entities\Context;

class Attribute
{
    public string $key;
    public array $values = [];

    function __construct(string $key, array $values)
    {
        $this->key = $key;
        $this->values = $values;
    }
}