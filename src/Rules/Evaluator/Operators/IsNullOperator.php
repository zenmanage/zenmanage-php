<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator\Operators;

final class IsNullOperator implements OperatorInterface
{
    public function supports(string $operator): bool
    {
        return $operator === 'isnull';
    }

    public function evaluate(mixed $actual, mixed $expected): bool
    {
        return $actual === null;
    }
}
