<?php

declare(strict_types=1);

namespace Zenmanage\Rules;

use JsonSerializable;

/**
 * Represents a value that a rule or target returns.
 */
final class RuleValue implements JsonSerializable
{
    public function __construct(
        private readonly string $version,
        private readonly mixed $value,
    ) {
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $version = isset($data['version']) && is_string($data['version']) ? $data['version'] : '';

        return new self(
            version: $version,
            value: $data['value'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => $this->version,
            'value' => $this->value,
        ];
    }
}
