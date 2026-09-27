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
     * Return a clone of this manager with the evaluation context set.
     *
     * The clone snapshots the parent's currently-loaded flags at the moment
     * it's created and does not share flag storage with it afterwards —
     * calling refreshRules() on either the clone or the parent only updates
     * that instance, never the other.
     */
    public function withContext(Context $context): self;

    /**
     * Return a clone of this manager with default values set.
     *
     * See withContext() for the clone/refresh isolation semantics — they
     * apply identically here.
     */
    public function withDefaults(DefaultsCollection $defaults): self;

    /**
     * Report usage of a flag.
     *
     * @param string $key The flag key
     * @param Context|null $context Optional context to send for tracking
     * @param mixed $defaultValue Optional default value the caller fell back to, sent for persistence
     */
    public function reportUsage(string $key, ?Context $context = null, mixed $defaultValue = null): void;

    /**
     * Refresh rules from the API into this instance only.
     *
     * A manager obtained via withContext()/withDefaults() does not share
     * flag storage with the instance it was cloned from, so refreshing one
     * never affects the other.
     */
    public function refreshRules(): void;
}
