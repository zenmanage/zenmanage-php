<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator\Conditions;

use Zenmanage\Flags\Context\Context;
use Zenmanage\Rules\Condition;

interface ConditionEvaluatorInterface
{
    /**
     * Whether this evaluator supports the given selector.
     */
    public function supports(string $selector): bool;

    /**
     * Evaluate the condition against the given context.
     */
    public function evaluate(Condition $condition, Context $context): bool;
}
