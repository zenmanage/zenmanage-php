<?php

declare(strict_types=1);

namespace Zenmanage\Api;

use Zenmanage\Api\Response\RulesResponse;
use Zenmanage\Flags\Context\Context;

/**
 * Interface for API client implementations.
 */
interface ApiClientInterface
{
    /**
     * Fetch all rules from the Zenmanage API.
     *
     * @param Context|null $context Optional context to send for tracking
     */
    public function getRules(?Context $context = null): RulesResponse;

    /**
     * Report usage of a flag (optional analytics).
     */
    public function reportUsage(string $flagKey): void;
}
