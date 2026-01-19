<?php

declare(strict_types=1);

namespace Zenmanage\Rules;

use JsonSerializable;

/**
 * Represents a rule with its criteria and resulting value.
 */
final class Rule implements JsonSerializable
{
    public function __construct(
        private readonly string $version,
        private readonly string $description,
        private readonly Condition $criteria,
        private readonly int $position,
        private readonly RuleValue $value,
    ) {
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCriteria(): Condition
    {
        return $this->criteria;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getValue(): RuleValue
    {
        return $this->value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $version = isset($data['version']) && is_string($data['version']) ? $data['version'] : '';
        $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : '';
        $position = isset($data['position']) && is_int($data['position']) ? $data['position'] : 0;

        return new self(
            version: $version,
            description: $description,
            criteria: Condition::fromArray(is_array($data['criteria'] ?? null) ? $data['criteria'] : []),
            position: $position,
            value: RuleValue::fromArray(is_array($data['value'] ?? null) ? $data['value'] : []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => $this->version,
            'description' => $this->description,
            'criteria' => $this->criteria->jsonSerialize(),
            'position' => $this->position,
            'value' => $this->value->jsonSerialize(),
        ];
    }
}
