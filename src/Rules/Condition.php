<?php

declare(strict_types=1);

namespace Zenmanage\Rules;

use JsonSerializable;

/**
 * Represents a condition within a rule (criteria for matching).
 */
final class Condition implements JsonSerializable
{
    /**
     * @param ConditionValue[] $values
     */
    public function __construct(
        private readonly string $selector,
        private readonly ?string $selectorSubtype,
        private readonly string $comparer,
        private readonly array $values,
    ) {
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getSelectorSubtype(): ?string
    {
        return $this->selectorSubtype;
    }

    public function getComparer(): string
    {
        return $this->comparer;
    }

    /**
     * @return ConditionValue[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $values = [];

        if (isset($data['values']) && is_array($data['values'])) {
            foreach ($data['values'] as $valueData) {
                // Handle both object format (segment/context) and string format (attribute)
                if (is_array($valueData)) {
                    $values[] = ConditionValue::fromArray($valueData);
                } elseif (is_string($valueData)) {
                    // For attribute selector, values are plain strings
                    // Convert to ConditionValue format with empty type
                    $values[] = new ConditionValue($valueData, '');
                }
            }
        }

        $selector = isset($data['selector']) && is_string($data['selector']) ? $data['selector'] : '';
        $comparer = isset($data['comparer']) && is_string($data['comparer']) ? $data['comparer'] : '';

        return new self(
            selector: $selector,
            selectorSubtype: isset($data['selector_subtype']) && is_string($data['selector_subtype']) ? $data['selector_subtype'] : null,
            comparer: $comparer,
            values: $values,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'selector' => $this->selector,
            'selector_subtype' => $this->selectorSubtype,
            'comparer' => $this->comparer,
            'values' => array_map(fn ($v) => $v->jsonSerialize(), $this->values),
        ];
    }
}
