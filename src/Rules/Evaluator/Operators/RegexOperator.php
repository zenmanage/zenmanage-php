<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator\Operators;

final class RegexOperator implements OperatorInterface
{
    public function supports(string $operator): bool
    {
        return $operator === 'regex';
    }

    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_string($actual) || !is_string($expected)) {
            return false;
        }

        try {
            return preg_match($expected, $actual) === 1;
        } catch (\Exception) {
            return false;
        }
    }
}
