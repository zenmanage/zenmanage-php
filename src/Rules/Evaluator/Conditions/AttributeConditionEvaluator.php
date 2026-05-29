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

        // For list-based operators (in / not_in), pass all condition values as an array
        if ($this->isListOperator($comparer)) {
            $expectedArray = array_map(
                static fn ($cv) => $cv->getIdentifier(),
                $conditionValues
            );
            foreach ($attributeValues as $attributeValue) {
                if ($this->operatorEvaluator->evaluate($comparer, $attributeValue, $expectedArray)) {
                    return true;
                }
            }

            return false;
        }

        // For negated operators (notequal, notcontains, notstartswith, notendswith, …),
        // evaluate the positive version across ALL attribute values and negate the aggregate.
        // "notcontains X" means NO attribute value contains X — not "any value doesn't contain X".
        if ($this->isNegatedOperator($comparer)) {
            $positiveComparer = $this->toPositiveOperator($comparer);
            foreach ($attributeValues as $attributeValue) {
                foreach ($conditionValues as $conditionValue) {
                    $expectedValue = $conditionValue->getIdentifier();
                    if ($this->operatorEvaluator->evaluate($positiveComparer, $attributeValue, $expectedValue)) {
                        return false; // a positive match found → negated result is false
                    }
                }
            }

            return true; // no positive match found → negated result is true
        }

        foreach ($attributeValues as $attributeValue) {
            foreach ($conditionValues as $conditionValue) {
                $expectedValue = $conditionValue->getIdentifier();

                $result = $this->operatorEvaluator->evaluate(
                    $comparer,
                    $attributeValue,
                    $expectedValue
                );

                if ($result === true) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isListOperator(string $operator): bool
    {
        $normalized = strtolower(str_replace(['-', ' ', '_'], '', $operator));

        return in_array($normalized, ['in', 'notin'], true);
    }

    private function isNegatedOperator(string $operator): bool
    {
        $op = strtolower(str_replace(['-', ' '], '', $operator));

        return str_starts_with($op, 'not_') || str_starts_with($op, 'not');
    }

    private function toPositiveOperator(string $operator): string
    {
        $op = strtolower(str_replace(['-', ' '], '', $operator));
        if (str_starts_with($op, 'not_')) {
            return substr($op, 4);
        }
        if (str_starts_with($op, 'not')) {
            return substr($op, 3);
        }

        return $op;
    }
}
