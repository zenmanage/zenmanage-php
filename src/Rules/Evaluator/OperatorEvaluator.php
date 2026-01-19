<?php

declare(strict_types=1);

namespace Zenmanage\Rules\Evaluator;

use Zenmanage\Exception\EvaluationException;
use Zenmanage\Rules\Evaluator\Operators\ContainsOperator;
use Zenmanage\Rules\Evaluator\Operators\EndsWithOperator;
use Zenmanage\Rules\Evaluator\Operators\EqualOperator;
use Zenmanage\Rules\Evaluator\Operators\GreaterThanOperator;
use Zenmanage\Rules\Evaluator\Operators\GreaterThanOrEqualOperator;
use Zenmanage\Rules\Evaluator\Operators\InOperator;
use Zenmanage\Rules\Evaluator\Operators\IsNullOperator;
use Zenmanage\Rules\Evaluator\Operators\LessThanOperator;
use Zenmanage\Rules\Evaluator\Operators\LessThanOrEqualOperator;
use Zenmanage\Rules\Evaluator\Operators\OperatorInterface;
use Zenmanage\Rules\Evaluator\Operators\RegexOperator;
use Zenmanage\Rules\Evaluator\Operators\StartsWithOperator;

/**
 * Evaluates operators (equal, not_equal, contains, etc.) on values.
 */
final class OperatorEvaluator
{
    /** @var OperatorInterface[] */
    private array $operators;

    /**
     * @param OperatorInterface[]|null $operators
     */
    public function __construct(?array $operators = null)
    {
        // Default registry of supported operators
        $this->operators = $operators ?? [
            new EqualOperator(),
            new InOperator(),
            new GreaterThanOperator(),
            new GreaterThanOrEqualOperator(),
            new LessThanOperator(),
            new LessThanOrEqualOperator(),
            new IsNullOperator(),
            new ContainsOperator(),
            new StartsWithOperator(),
            new EndsWithOperator(),
            new RegexOperator(),
        ];
    }

    /**
     * Evaluate an operator with given actual and expected values.
     */
    public function evaluate(string $operator, mixed $actual, mixed $expected): bool
    {
        [$base, $negate] = $this->normalizeOperator($operator);

        $impl = $this->findOperator($base);
        if ($impl === null) {
            throw new EvaluationException("Unknown operator: {$operator}");
        }

        $result = $impl->evaluate($actual, $expected);

        return $negate ? !$result : $result;
    }

    private function findOperator(string $operator): ?OperatorInterface
    {
        foreach ($this->operators as $impl) {
            if ($impl->supports($operator)) {
                return $impl;
            }
        }

        return null;
    }

    /**
     * Normalize incoming operator tokens to canonical names and extract negation.
     */
    /**
     * @return array{0: string, 1: bool}
     */
    private function normalizeOperator(string $operator): array
    {
        $op = strtolower($operator);
        $op = str_replace(['-', ' '], '', $op);

        $negate = false;
        if (str_starts_with($op, 'not_')) {
            $negate = true;
            $op = substr($op, 4);
        } elseif (str_starts_with($op, 'not')) {
            $negate = true;
            $op = substr($op, 3);
        }

        // Remove underscores to get canonical form (e.g., starts_with -> startswith)
        $op = str_replace('_', '', $op);

        // Synonyms mapping
        $synonyms = [
            'null' => 'isnull',
            'isnull' => 'isnull',
            'equal' => 'equal',
            'in' => 'in',
            'gt' => 'gt',
            'gte' => 'gte',
            'lt' => 'lt',
            'lte' => 'lte',
            'contains' => 'contains',
            'startswith' => 'startswith',
            'endswith' => 'endswith',
            'regex' => 'regex',
        ];

        $base = $synonyms[$op] ?? $op;

        return [$base, $negate];
    }
}
