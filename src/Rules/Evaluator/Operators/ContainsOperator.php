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
        if (is_string($actual) && is_string($expected)) {
            return str_contains($actual, $expected);
        }

        if (is_array($actual)) {
            return in_array($expected, $actual, true);
        }

        return false;
    }
}
