<?php

declare(strict_types=1);

namespace Zenmanage\Flags;

use JsonSerializable;

/**
 * Collection of default flag values to use when a flag is not found.
 */
final class DefaultsCollection implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $defaults = [];

    /**
     * @param array<string, mixed> $defaults
     */
    public function __construct(array $defaults = [])
    {
        $this->defaults = $defaults;
    }

    /**
     * Create a collection from an array of key => value pairs.
     *
     * @param array<string, mixed> $defaults
     */
    public static function fromArray(array $defaults): self
    {
        return new self($defaults);
    }

    /**
     * Add a default value for a flag key.
     */
    public function set(string $key, mixed $value): self
    {
        $this->defaults[$key] = $value;

        return $this;
    }

    /**
     * Get the default value for a flag key.
     */
    public function get(string $key): mixed
    {
        return $this->defaults[$key] ?? null;
    }

    /**
     * Check if a default exists for a flag key.
     */
    public function has(string $key): bool
    {
        return isset($this->defaults[$key]);
    }

    /**
     * Get all defaults.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->defaults;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->defaults;
    }
}
