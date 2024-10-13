<?php

namespace Zenmanage\Flags\Request\Entities\Context;

class Context
{
    public string $type;
    public string $name;
    public string $identifier;

    public ?array $attributes = null;

    public function __construct(string $type, string $name, string $identifier, ?array $attributes = null)
    {
        $this->type = $type;
        $this->name = $name;
        $this->identifier = $identifier;
        $this->attributes = $attributes;
    }
}
