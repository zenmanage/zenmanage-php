<?php

declare(strict_types=1);

namespace Zenmanage\Rules;

use JsonSerializable;

/**
 * Represents a value in a rule condition (e.g., segment identifier).
 */
final class ConditionValue implements JsonSerializable
{
    public function __construct(
        private readonly string $identifier,
        private readonly ?string $type,
    ) {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $identifier = isset($data['identifier']) && is_string($data['identifier']) ? $data['identifier'] : '';
        $type = null;

        if (array_key_exists('type', $data)) {
            $type = is_string($data['type']) ? $data['type'] : null;
        }

        return new self(
            identifier: $identifier,
            type: $type,
        );
    }

    /**
     * @return array{identifier: string, type: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'identifier' => $this->identifier,
            'type' => $this->type,
        ];
    }
}
