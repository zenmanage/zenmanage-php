<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Rules\Evaluator;

use PHPUnit\Framework\TestCase;
use Zenmanage\Exception\EvaluationException;
use Zenmanage\Rules\Evaluator\OperatorEvaluator;

final class OperatorEvaluatorTest extends TestCase
{
    private OperatorEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new OperatorEvaluator();
    }

    public function testEqualOperator(): void
    {
        $this->assertTrue($this->evaluator->evaluate('equal', 'test', 'test'));
        $this->assertFalse($this->evaluator->evaluate('equal', 'test', 'other'));
    }

    public function testNotEqualOperator(): void
    {
        $this->assertTrue($this->evaluator->evaluate('notequal', 'test', 'other'));
        $this->assertFalse($this->evaluator->evaluate('notequal', 'test', 'test'));
    }

    public function testContainsOperator(): void
    {
        $this->assertTrue($this->evaluator->evaluate('contains', 'hello world', 'world'));
        $this->assertFalse($this->evaluator->evaluate('contains', 'hello world', 'foo'));

        // Array contains
        $this->assertTrue($this->evaluator->evaluate('contains', ['a', 'b', 'c'], 'b'));
        $this->assertFalse($this->evaluator->evaluate('contains', ['a', 'b', 'c'], 'd'));
    }

    public function testStartsWithOperator(): void
    {
        $this->assertTrue($this->evaluator->evaluate('startswith', 'hello world', 'hello'));
        $this->assertFalse($this->evaluator->evaluate('startswith', 'hello world', 'world'));
    }

    public function testEndsWithOperator(): void
    {
        $this->assertTrue($this->evaluator->evaluate('endswith', 'hello world', 'world'));
        $this->assertFalse($this->evaluator->evaluate('endswith', 'hello world', 'hello'));
    }

    public function testGreaterThanOperator(): void
    {
        $this->assertTrue($this->evaluator->evaluate('gt', 10, 5));
        $this->assertFalse($this->evaluator->evaluate('gt', 5, 10));
        $this->assertFalse($this->evaluator->evaluate('gt', 5, 5));
    }

    public function testGreaterThanOrEqualOperator(): void
    {
        $this->assertTrue($this->evaluator->evaluate('gte', 10, 5));
        $this->assertTrue($this->evaluator->evaluate('gte', 5, 5));
        $this->assertFalse($this->evaluator->evaluate('gte', 5, 10));
    }

    public function testLessThanOperator(): void
    {
        $this->assertTrue($this->evaluator->evaluate('lt', 5, 10));
        $this->assertFalse($this->evaluator->evaluate('lt', 10, 5));
        $this->assertFalse($this->evaluator->evaluate('lt', 5, 5));
    }

    public function testLessThanOrEqualOperator(): void
    {
        $this->assertTrue($this->evaluator->evaluate('lte', 5, 10));
        $this->assertTrue($this->evaluator->evaluate('lte', 5, 5));
        $this->assertFalse($this->evaluator->evaluate('lte', 10, 5));
    }

    public function testInOperator(): void
    {
        $this->assertTrue($this->evaluator->evaluate('in', 'b', ['a', 'b', 'c']));
        $this->assertFalse($this->evaluator->evaluate('in', 'd', ['a', 'b', 'c']));
    }

    public function testNotInOperator(): void
    {
        $this->assertTrue($this->evaluator->evaluate('notin', 'd', ['a', 'b', 'c']));
        $this->assertFalse($this->evaluator->evaluate('notin', 'b', ['a', 'b', 'c']));
    }

    public function testRegexOperator(): void
    {
        $this->assertTrue($this->evaluator->evaluate('regex', 'test123', '/^test\d+$/'));
        $this->assertFalse($this->evaluator->evaluate('regex', 'test', '/^test\d+$/'));
    }

    public function testIsNullOperator(): void
    {
        $this->assertTrue($this->evaluator->evaluate('isnull', null, null));
        $this->assertFalse($this->evaluator->evaluate('isnull', 'test', null));
        $this->assertFalse($this->evaluator->evaluate('isnull', 0, null));
        $this->assertFalse($this->evaluator->evaluate('isnull', '', null));
        $this->assertFalse($this->evaluator->evaluate('isnull', false, null));
    }

    public function testNotNullOperator(): void
    {
        $this->assertFalse($this->evaluator->evaluate('notnull', null, null));
        $this->assertTrue($this->evaluator->evaluate('notnull', 'test', null));
        $this->assertTrue($this->evaluator->evaluate('notnull', 0, null));
        $this->assertTrue($this->evaluator->evaluate('notnull', '', null));
        $this->assertTrue($this->evaluator->evaluate('notnull', false, null));
        $this->assertTrue($this->evaluator->evaluate('notnull', [], null));
    }

    public function testUnknownOperator(): void
    {
        $this->expectException(EvaluationException::class);
        $this->expectExceptionMessage('Unknown operator: unknown_operator');

        $this->evaluator->evaluate('unknown_operator', 'test', 'test');
    }
}
