<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator\Operators;

final class ContainsOperator implements OperatorInterface
{
    public function supports(string $operator): bool
    {
        return $operator === 'contains';
    }

    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (is_string($actual) === true && is_string($expected) === true) {
            return str_contains($actual, $expected);
        }

        if (is_array($actual) === true) {
            return in_array($expected, $actual, true);
        }

        return false;
    }
}
