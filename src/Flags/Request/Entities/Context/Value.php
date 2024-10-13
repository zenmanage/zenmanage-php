<?php

namespace Zenmanage\Flags\Request\Entities\Context;

class Value
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }
}
