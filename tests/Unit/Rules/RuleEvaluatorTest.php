<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Zenmanage\Flags\Context\Context;
use Zenmanage\Flags\Flag;
use Zenmanage\Flags\Target;
use Zenmanage\Rules\Condition;
use Zenmanage\Rules\ConditionValue;
use Zenmanage\Rules\Evaluator\ConditionEvaluator;
use Zenmanage\Rules\Evaluator\OperatorEvaluator;
use Zenmanage\Rules\Evaluator\RuleEvaluator;
use Zenmanage\Rules\Rule;
use Zenmanage\Rules\RuleValue;

final class RuleEvaluatorTest extends TestCase
{
    /**
     * @param array<int, Rule> $rules
     */
    private function makeFlagWithRules(array $rules, mixed $targetValue): Flag
    {
        $target = new Target('t', null, null, null, new RuleValue('target-val', $targetValue));

        return new Flag('f', 'boolean', 'key', 'name', $target, $rules);
    }

    public function testReturnsFirstMatchingRuleValueInOrder(): void
    {
        $conditionEvaluator = new ConditionEvaluator(new OperatorEvaluator());
        $evaluator = new RuleEvaluator($conditionEvaluator);

        $ruleOne = new Rule(
            version: 'r1',
            description: 'Not matching',
            criteria: new Condition('segment', null, 'equal', [new ConditionValue('other', 'user')]),
            position: 2,
            value: new RuleValue('v1', ['boolean' => false]),
        );

        $ruleTwo = new Rule(
            version: 'r2',
            description: 'Matches context',
            criteria: new Condition('segment', null, 'equal', [new ConditionValue('user-1', 'user')]),
            position: 1,
            value: new RuleValue('v2', ['boolean' => true]),
        );

        $flag = $this->makeFlagWithRules([$ruleOne, $ruleTwo], ['boolean' => false]);
        $context = Context::single('user', 'user-1');

        $result = $evaluator->evaluate($flag, $context);

        $this->assertSame(['boolean' => true], $result);
    }

    public function testFallsBackToTargetValueWhenNoRulesMatch(): void
    {
        $conditionEvaluator = new ConditionEvaluator(new OperatorEvaluator());
        $evaluator = new RuleEvaluator($conditionEvaluator);

        $rule = new Rule(
            version: 'r1',
            description: 'Will not match',
            criteria: new Condition('segment', null, 'equal', [new ConditionValue('other', 'user')]),
            position: 1,
            value: new RuleValue('v1', ['boolean' => false]),
        );

        $flag = $this->makeFlagWithRules([$rule], ['boolean' => true]);
        $context = Context::single('user', 'user-1');

        $result = $evaluator->evaluate($flag, $context);

        $this->assertSame(['boolean' => true], $result);
    }
}
