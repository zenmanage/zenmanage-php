<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator\Conditions;

use Zenmanage\Flags\Context\Context;
use Zenmanage\Rules\Condition;
use Zenmanage\Rules\Evaluator\OperatorEvaluator;

final class AttributeConditionEvaluator implements ConditionEvaluatorInterface
{
    public function __construct(private readonly OperatorEvaluator $operatorEvaluator)
    {
    }

    public function supports(string $selector): bool
    {
        return $selector === 'attribute';
    }

    public function evaluate(Condition $condition, Context $context): bool
    {
        $comparer = $condition->getComparer();
        $selectorSubtype = $condition->getSelectorSubtype();
        $conditionValues = $condition->getValues();

        if ($selectorSubtype === null) {
            return false;
        }

        $attribute = $context->getAttribute($selectorSubtype);
        if ($attribute === null) {
            return false;
        }

        $attributeValues = $attribute->getValues();

        foreach ($attributeValues as $attributeValue) {
            foreach ($conditionValues as $conditionValue) {
                $expectedValue = $conditionValue->getIdentifier();

                $result = $this->operatorEvaluator->evaluate(
                    $comparer,
                    $attributeValue,
                    $expectedValue
                );

                if ($result) {
                    return true;
                }
            }
        }

        return false;
    }
}
