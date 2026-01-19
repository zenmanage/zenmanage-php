<?php

declare(strict_types=1);

namespace Zenmanage\Flags\Context;

use JsonSerializable;

/**
 * Represents an attribute value within a context.
 */
final class Value implements JsonSerializable
{
    public function __construct(
        private readonly mixed $value,
    ) {
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function asString(): string
    {
        if (is_string($this->value)) {
            return $this->value;
        }

        if (is_numeric($this->value)) {
            return (string) $this->value;
        }

        if (is_bool($this->value)) {
            return $this->value ? 'true' : 'false';
        }

        if (is_array($this->value)) {
            return json_encode($this->value) ?: '';
        }

        return '';
    }

    public function asNumber(): int|float
    {
        if (is_numeric($this->value)) {
            return $this->value + 0; // Convert to appropriate numeric type
        }

        return 0;
    }

    public function asBool(): bool
    {
        if (is_bool($this->value)) {
            return $this->value;
        }

        if (is_string($this->value)) {
            return in_array(strtolower($this->value), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $this->value;
    }

    /**
     * @return array<mixed>
     */
    public function asArray(): array
    {
        if (is_array($this->value)) {
            return $this->value;
        }

        return [$this->value];
    }

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->asString();
    }
}
