<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator\Operators;

final class InOperator implements OperatorInterface
{
    public function supports(string $operator): bool
    {
        return $operator === 'in';
    }

    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (is_array($expected)) {
            return in_array($actual, $expected, true);
        }

        return false;
    }
}
