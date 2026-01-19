<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator;

use Zenmanage\Flags\Context\Context;
use Zenmanage\Flags\Flag;
use Zenmanage\Rules\Rule;

/**
 * Evaluates rules for a flag against a context to determine the final value.
 */
final class RuleEvaluator
{
    public function __construct(
        private readonly ConditionEvaluator $conditionEvaluator,
    ) {
    }

    /**
     * Evaluate all rules for a flag and return the matching value.
     * Returns the first matching rule's value, or the flag's target value if no rules match.
     */
    public function evaluate(Flag $flag, Context $context): mixed
    {
        $rules = $flag->getRules();

        // Sort rules by position (lowest first)
        usort($rules, fn (Rule $a, Rule $b) => $a->getPosition() <=> $b->getPosition());

        // Evaluate each rule in order
        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $context)) {
                // Return the rule's value
                return $rule->getValue()->getValue();
            }
        }

        // No rules matched, return the target value
        return $flag->getTarget()->getValue()->getValue();
    }

    /**
     * Evaluate a single rule against the context.
     */
    private function evaluateRule(Rule $rule, Context $context): bool
    {
        $criteria = $rule->getCriteria();

        return $this->conditionEvaluator->evaluate($criteria, $context);
    }
}
