<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator\Operators;

interface OperatorInterface
{
    /**
     * Whether this operator implementation supports the given operator keyword.
     */
    public function supports(string $operator): bool;

    /**
     * Evaluate the operator against actual and expected values.
     */
    public function evaluate(mixed $actual, mixed $expected): bool;
}
