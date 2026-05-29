<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator\Operators;

final class GreaterThanOrEqualOperator implements OperatorInterface
{
    public function supports(string $operator): bool
    {
        return $operator === 'gte';
    }

    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (is_numeric($actual) === false || is_numeric($expected) === false) {
            return false;
        }

        return $actual >= $expected;
    }
}
