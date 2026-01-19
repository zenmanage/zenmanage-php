<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator\Operators;

final class EndsWithOperator implements OperatorInterface
{
    public function supports(string $operator): bool
    {
        return $operator === 'endswith';
    }

    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_string($actual) || !is_string($expected)) {
            return false;
        }

        return str_ends_with($actual, $expected);
    }
}
