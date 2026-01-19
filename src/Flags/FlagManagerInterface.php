<?php

declare(strict_types=1);

namespace Zenmanage\Flags;

use Zenmanage\Flags\Context\Context;

/**
 * Interface for flag manager implementations.
 */
interface FlagManagerInterface
{
    /**
     * Get all available flags.
     *
     * @return Flag[]
     */
    public function all(): array;

    /**
     * Get a single flag by its key.
     *
     * @param mixed $default Optional default value if flag is not found
     */
    public function single(string $key, mixed $default = null): Flag;

    /**
     * Set the evaluation context.
     */
    public function withContext(Context $context): self;

    /**
     * Set default values for flags.
     */
    public function withDefaults(DefaultsCollection $defaults): self;

    /**
     * Report usage of a flag.
     *
     * @param string $key The flag key
     * @param Context|null $context Optional context to send for tracking
     */
    public function reportUsage(string $key, ?Context $context = null): void;

    /**
     * Refresh rules from the API.
     */
    public function refreshRules(): void;
}
