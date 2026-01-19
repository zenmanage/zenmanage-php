<?php

declare(strict_types=1);

namespace Zenmanage\Api\Response;

use Zenmanage\Exception\InvalidRulesException;
use Zenmanage\Flags\Flag;

/**
 * Represents the response from the rules API endpoint.
 */
final class RulesResponse
{
    /**
     * @param Flag[] $flags
     */
    public function __construct(
        private readonly string $version,
        private readonly array $flags,
    ) {
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return Flag[]
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * Get a flag by its key.
     */
    public function getFlagByKey(string $key): ?Flag
    {
        foreach ($this->flags as $flag) {
            if ($flag->getKey() === $key) {
                return $flag;
            }
        }

        return null;
    }

    /**
     * Parse the API response JSON into a RulesResponse object.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['version'])) {
            throw new InvalidRulesException('Rules response missing "version" field');
        }

        if (!isset($data['flags']) || !is_array($data['flags'])) {
            throw new InvalidRulesException('Rules response missing or invalid "flags" field');
        }

        $flags = [];

        foreach ($data['flags'] as $flagData) {
            try {
                $flags[] = Flag::fromArray($flagData);
            } catch (\Exception $e) {
                // Skip invalid flags but log the error
                // In production, you might want to use a logger here
                continue;
            }
        }

        $version = $data['version'];
        if (!is_string($version)) {
            $version = '';
        }

        return new self(
            version: $version,
            flags: $flags,
        );
    }
}
