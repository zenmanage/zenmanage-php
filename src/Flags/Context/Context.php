<?php

declare(strict_types=1);

namespace Zenmanage\Flags\Context;

use JsonSerializable;

/**
 * Represents the evaluation context containing attributes for rule matching.
 */
final class Context implements JsonSerializable
{
    /** @var array<string, Attribute> */
    private array $attributes = [];

    /**
     * @param string $type The context type (e.g., 'user', 'organization', 'app')
     * @param string|null $name The display name (e.g., 'John Doe', 'Acme Corp')
     * @param string|null $identifier The unique identifier (e.g., user ID, org ID)
     * @param Attribute[] $attributes Additional attributes for rule matching
     */
    public function __construct(
        private string $type,
        private ?string $name = null,
        private ?string $identifier = null,
        array $attributes = []
    ) {
        foreach ($attributes as $attribute) {
            $this->addAttribute($attribute);
        }
    }

    /**
     * Create a context from an array.
     *
     * @param array<string, mixed> $data Array with 'type', 'name', 'identifier', and 'attributes'
     */
    public static function fromArray(array $data): self
    {
        $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : 'user';
        $name = isset($data['name']) && is_string($data['name']) ? $data['name'] : null;
        $identifier = isset($data['identifier']) && is_string($data['identifier']) ? $data['identifier'] : null;

        $attributes = [];

        // Process attributes array if present
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            foreach ($data['attributes'] as $attrData) {
                if (!is_array($attrData)) {
                    continue;
                }

                $key = $attrData['key'] ?? null;
                if (!is_string($key)) {
                    continue;
                }

                $values = [];
                if (isset($attrData['values']) && is_array($attrData['values'])) {
                    foreach ($attrData['values'] as $valueData) {
                        if (is_array($valueData) && isset($valueData['value'])) {
                            $values[] = (string) $valueData['value'];
                        } elseif (is_string($valueData)) {
                            $values[] = $valueData;
                        }
                    }
                }

                if (!empty($values)) {
                    $attributes[] = new Attribute($key, $values);
                }
            }
        }

        return new self($type, $name, $identifier, $attributes);
    }

    /**
     * Create a simple context with identifier and optional name.
     *
     * @param string $type The context type (e.g., 'user', 'organization')
     * @param string $identifier The unique identifier
     * @param string|null $name Optional display name
     */
    public static function single(string $type, string $identifier, ?string $name = null): self
    {
        return new self($type, $name, $identifier, []);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    /**
     * @deprecated Use getIdentifier() instead
     */
    public function getId(): ?string
    {
        return $this->identifier;
    }

    public function addAttribute(Attribute $attribute): self
    {
        $this->attributes[$attribute->getKey()] = $attribute;

        return $this;
    }

    public function getAttribute(string $type): ?Attribute
    {
        return $this->attributes[$type] ?? null;
    }

    public function hasAttribute(string $type): bool
    {
        return isset($this->attributes[$type]);
    }

    /**
     * @return Attribute[]
     */
    public function getAttributes(): array
    {
        return array_values($this->attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'type' => $this->type,
        ];

        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        if ($this->identifier !== null) {
            $result['identifier'] = $this->identifier;
        }

        if (!empty($this->attributes)) {
            $result['attributes'] = array_values(array_map(
                fn (Attribute $attr) => $attr->jsonSerialize(),
                $this->attributes
            ));
        }

        return $result;
    }

    public function __toString(): string
    {
        return json_encode($this->jsonSerialize()) ?: '{}';
    }
}
