<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator;

use Zenmanage\Flags\Context\Context;
use Zenmanage\Rules\Condition;
use Zenmanage\Rules\Evaluator\Conditions\AttributeConditionEvaluator;
use Zenmanage\Rules\Evaluator\Conditions\ConditionEvaluatorInterface;
use Zenmanage\Rules\Evaluator\Conditions\ContextConditionEvaluator;
use Zenmanage\Rules\Evaluator\Conditions\SegmentConditionEvaluator;

/**
 * Evaluates conditions against a context.
 */
final class ConditionEvaluator
{
    /** @var ConditionEvaluatorInterface[] */
    private array $evaluators;

    public function __construct(private readonly OperatorEvaluator $operatorEvaluator)
    {
        // Default registry of condition evaluators
        $this->evaluators = [
            new SegmentConditionEvaluator($this->operatorEvaluator),
            new ContextConditionEvaluator($this->operatorEvaluator),
            new AttributeConditionEvaluator($this->operatorEvaluator),
        ];
    }

    /**
     * Evaluate a condition against the given context.
     */
    public function evaluate(Condition $condition, Context $context): bool
    {
        $selector = $condition->getSelector();

        foreach ($this->evaluators as $evaluator) {
            if ($evaluator->supports($selector)) {
                return $evaluator->evaluate($condition, $context);
            }
        }

        // Unknown selector
        return false;
    }
}
