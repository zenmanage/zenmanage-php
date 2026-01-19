<?php

declare(strict_types=1);

namespace Zenmanage\Flags\Context;

use JsonSerializable;

/**
 * Represents a single attribute in a context (e.g., "account_ulid", "plan_id").
 */
final class Attribute implements JsonSerializable
{
    /**
     * @param string[] $values
     */
    public function __construct(
        private readonly string $key,
        private readonly array $values,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function matches(string $key, ?string $value = null): bool
    {
        if ($this->key !== $key) {
            return false;
        }

        if ($value !== null && !in_array($value, $this->values, true)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'values' => array_map(fn ($v) => ['value' => $v], $this->values),
        ];
    }

    public function __toString(): string
    {
        return "{$this->key}:" . implode(',', $this->values);
    }
}
