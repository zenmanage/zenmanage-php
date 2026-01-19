<?php

declare(strict_types=1);

namespace Zenmanage\Rules;

use Zenmanage\Flags\Context\Context;
use Zenmanage\Flags\Flag;
use Zenmanage\Rules\Evaluator\ConditionEvaluator;
use Zenmanage\Rules\Evaluator\OperatorEvaluator;
use Zenmanage\Rules\Evaluator\RuleEvaluator;

/**
 * Main rule evaluation engine.
 */
final class RuleEngine implements RuleEngineInterface
{
    private readonly RuleEvaluator $ruleEvaluator;

    public function __construct()
    {
        $operatorEvaluator = new OperatorEvaluator();
        $conditionEvaluator = new ConditionEvaluator($operatorEvaluator);
        $this->ruleEvaluator = new RuleEvaluator($conditionEvaluator);
    }

    public function evaluate(Flag $flag, Context $context): mixed
    {
        return $this->ruleEvaluator->evaluate($flag, $context);
    }
}
