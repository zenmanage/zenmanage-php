<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator\Conditions;

use Zenmanage\Flags\Context\Context;
use Zenmanage\Rules\Condition;
use Zenmanage\Rules\Evaluator\OperatorEvaluator;

final class ContextConditionEvaluator implements ConditionEvaluatorInterface
{
    public function __construct(private readonly OperatorEvaluator $operatorEvaluator)
    {
    }

    public function supports(string $selector): bool
    {
        return $selector === 'context';
    }

    public function evaluate(Condition $condition, Context $context): bool
    {
        $comparer = $condition->getComparer();
        $conditionValues = $condition->getValues();

        $contextIdentifier = $context->getIdentifier();
        if ($contextIdentifier === null) {
            return false;
        }

        $contextType = $context->getType();

        foreach ($conditionValues as $conditionValue) {
            if ($conditionValue->getType() !== $contextType) {
                continue;
            }

            $result = $this->operatorEvaluator->evaluate(
                $comparer,
                $contextIdentifier,
                $conditionValue->getIdentifier()
            );

            if ($result) {
                return true;
            }
        }

        return false;
    }
}
