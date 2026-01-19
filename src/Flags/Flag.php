<?php

declare(strict_types=1);

namespace Zenmanage\Flags;

use JsonSerializable;
use Zenmanage\Rules\Rule;

/**
 * Represents a feature flag with its metadata, rules, and target value.
 */
final class Flag implements JsonSerializable
{
    /**
     * @param Rule[] $rules
     */
    public function __construct(
        private readonly string $version,
        private readonly string $type,
        private readonly string $key,
        private readonly string $name,
        private readonly Target $target,
        private readonly array $rules,
    ) {
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTarget(): Target
    {
        return $this->target;
    }

    /**
     * @return Rule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Check if this flag is of type boolean and is enabled (true).
     */
    public function isEnabled(): bool
    {
        if ($this->type !== 'boolean') {
            return false;
        }

        $value = $this->target->getValue()->getValue();

        if (is_array($value) && isset($value['boolean'])) {
            return (bool) $value['boolean'];
        }

        return (bool) $value;
    }

    /**
     * Get the flag value as a boolean.
     */
    public function asBool(): bool
    {
        $value = $this->target->getValue()->getValue();

        if (is_array($value) && isset($value['boolean'])) {
            return (bool) $value['boolean'];
        }

        return (bool) $value;
    }

    /**
     * Get the flag value as a string.
     */
    public function asString(): string
    {
        $value = $this->target->getValue()->getValue();

        if (is_array($value) && isset($value['string'])) {
            return (string) $value['string'];
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Get the flag value as a number.
     */
    public function asNumber(): int|float
    {
        $value = $this->target->getValue()->getValue();

        if (is_array($value) && isset($value['number'])) {
            return $value['number'];
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        return 0;
    }

    /**
     * Get the raw flag value.
     */
    public function getValue(): mixed
    {
        return $this->target->getValue()->getValue();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rules = [];

        if (isset($data['rules']) && is_array($data['rules'])) {
            foreach ($data['rules'] as $ruleData) {
                $rules[] = Rule::fromArray($ruleData);
            }
        }

        $version = isset($data['version']) && is_string($data['version']) ? $data['version'] : '';
        $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : 'boolean';
        $key = isset($data['key']) && is_string($data['key']) ? $data['key'] : '';
        $name = isset($data['name']) && is_string($data['name']) ? $data['name'] : '';

        return new self(
            version: $version,
            type: $type,
            key: $key,
            name: $name,
            target: Target::fromArray(is_array($data['target'] ?? null) ? $data['target'] : []),
            rules: $rules,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => $this->version,
            'type' => $this->type,
            'key' => $this->key,
            'name' => $this->name,
            'target' => $this->target->jsonSerialize(),
            'rules' => array_map(fn ($r) => $r->jsonSerialize(), $this->rules),
        ];
    }

    public function __toString(): string
    {
        return $this->key;
    }
}
