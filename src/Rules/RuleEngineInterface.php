<?php

declare(strict_types=1);

namespace Zenmanage\Rules;

use Zenmanage\Flags\Context\Context;
use Zenmanage\Flags\Flag;

/**
 * Interface for rule evaluation engines.
 */
interface RuleEngineInterface
{
    /**
     * Evaluate a flag's rules against a context and return the resulting value.
     */
    public function evaluate(Flag $flag, Context $context): mixed;
}
