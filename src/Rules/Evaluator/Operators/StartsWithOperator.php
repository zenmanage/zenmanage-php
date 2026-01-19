<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator\Operators;

final class StartsWithOperator implements OperatorInterface
{
    public function supports(string $operator): bool
    {
        return $operator === 'startswith';
    }

    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_string($actual) || !is_string($expected)) {
            return false;
        }
        return str_starts_with($actual, $expected);
    }
}
