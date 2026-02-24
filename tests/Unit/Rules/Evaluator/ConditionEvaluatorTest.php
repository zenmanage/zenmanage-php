<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Rules\Evaluator;

use PHPUnit\Framework\TestCase;
use Zenmanage\Flags\Context\Context;
use Zenmanage\Rules\Condition;
use Zenmanage\Rules\ConditionValue;
use Zenmanage\Rules\Evaluator\ConditionEvaluator;
use Zenmanage\Rules\Evaluator\OperatorEvaluator;

final class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ConditionEvaluator(new OperatorEvaluator());
    }

    public function test_segment_selector_matches_when_type_and_id_equal(): void
    {
        $condition = new Condition(
            selector: 'segment',
            selectorSubtype: null,
            comparer: 'equal',
            values: [
                new ConditionValue('140.248.31.37', 'user'),
                new ConditionValue('192.168.1.1', 'user'),
                new ConditionValue('10.0.0.1', 'user'),
            ]
        );

        $context = Context::single('user', '140.248.31.37', 'Test User');

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertTrue($result);
    }

    public function test_segment_selector_does_not_match_when_id_not_in_list(): void
    {
        $condition = new Condition(
            selector: 'segment',
            selectorSubtype: null,
            comparer: 'equal',
            values: [
                new ConditionValue('140.248.31.37', 'user'),
                new ConditionValue('192.168.1.1', 'user'),
            ]
        );

        $context = Context::single('user', '10.0.0.1', 'Test User');

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertFalse($result);
    }

    public function test_segment_selector_does_not_match_when_type_different(): void
    {
        $condition = new Condition(
            selector: 'segment',
            selectorSubtype: null,
            comparer: 'equal',
            values: [
                new ConditionValue('140.248.31.37', 'user'),
                new ConditionValue('acme-corp', 'organization'),
            ]
        );

        // Context is user type, but trying to match organization segment
        $context = Context::single('user', 'acme-corp', 'Acme Corp');

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertFalse($result);
    }

    public function test_segment_selector_matches_organization_type(): void
    {
        $condition = new Condition(
            selector: 'segment',
            selectorSubtype: null,
            comparer: 'equal',
            values: [
                new ConditionValue('acme-corp', 'organization'),
                new ConditionValue('beta-testers', 'organization'),
            ]
        );

        $context = Context::single('organization', 'acme-corp', 'Acme Corporation');

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertTrue($result);
    }

    public function test_segment_selector_matches_by_identifier_when_rule_type_is_null(): void
    {
        $condition = new Condition(
            selector: 'segment',
            selectorSubtype: null,
            comparer: 'equal',
            values: [
                new ConditionValue('acme-corp', null),
            ]
        );

        $context = Context::single('user', 'acme-corp', 'Acme User');

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertTrue($result);
    }

    public function test_segment_selector_returns_false_when_context_has_no_id(): void
    {
        $condition = new Condition(
            selector: 'segment',
            selectorSubtype: null,
            comparer: 'equal',
            values: [
                new ConditionValue('test-id', 'user'),
            ]
        );

        $context = new Context('user', 'Test User', null, []);

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertFalse($result);
    }

    public function test_segment_selector_with_contains_operator(): void
    {
        $condition = new Condition(
            selector: 'segment',
            selectorSubtype: null,
            comparer: 'contains',
            values: [
                new ConditionValue('admin', 'user'),
            ]
        );

        $context = Context::single('user', 'admin-user-123', 'Admin User');

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertTrue($result);
    }

    public function test_segment_selector_with_starts_with_operator(): void
    {
        $condition = new Condition(
            selector: 'segment',
            selectorSubtype: null,
            comparer: 'starts_with',
            values: [
                new ConditionValue('192.168.', 'user'),
            ]
        );

        $context = Context::single('user', '192.168.1.100', 'Local User');

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertTrue($result);
    }

    public function test_unknown_selector_returns_false(): void
    {
        $condition = new Condition(
            selector: 'unknown_selector',
            selectorSubtype: null,
            comparer: 'equal',
            values: []
        );

        $context = Context::single('user', 'test-id', 'Test User');

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertFalse($result);
    }

    // Context selector tests (identical to segment but different name)

    public function test_context_selector_matches_when_type_and_id_equal(): void
    {
        $condition = new Condition(
            selector: 'context',
            selectorSubtype: null,
            comparer: 'equal',
            values: [
                new ConditionValue('john-doe', 'user'),
                new ConditionValue('jane-doe', 'user'),
            ]
        );

        $context = Context::single('user', 'john-doe', 'John Doe');

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertTrue($result);
    }

    public function test_context_selector_does_not_match_when_id_not_in_list(): void
    {
        $condition = new Condition(
            selector: 'context',
            selectorSubtype: null,
            comparer: 'equal',
            values: [
                new ConditionValue('john-doe', 'user'),
                new ConditionValue('jane-doe', 'user'),
            ]
        );

        $context = Context::single('user', 'bob-smith', 'Bob Smith');

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertFalse($result);
    }

    public function test_context_selector_does_not_match_when_type_different(): void
    {
        $condition = new Condition(
            selector: 'context',
            selectorSubtype: null,
            comparer: 'equal',
            values: [
                new ConditionValue('acme-corp', 'organization'),
            ]
        );

        $context = Context::single('user', 'acme-corp', 'Acme User');

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertFalse($result);
    }

    public function test_context_selector_matches_by_identifier_when_rule_type_is_null(): void
    {
        $condition = new Condition(
            selector: 'context',
            selectorSubtype: null,
            comparer: 'equal',
            values: [
                new ConditionValue('acme-corp', null),
            ]
        );

        $context = Context::single('user', 'acme-corp', 'Acme User');

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertTrue($result);
    }

    // Attribute selector tests

    public function test_attribute_selector_matches_when_attribute_equals_value(): void
    {
        $condition = new Condition(
            selector: 'attribute',
            selectorSubtype: 'plan',
            comparer: 'equal',
            values: [
                new ConditionValue('enterprise', ''),
                new ConditionValue('pro', ''),
            ]
        );

        $context = Context::fromArray([
            'type' => 'user',
            'identifier' => 'user-123',
            'name' => 'Test User',
            'attributes' => [
                ['key' => 'plan', 'values' => [['value' => 'enterprise']]],
            ],
        ]);

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertTrue($result);
    }

    public function test_attribute_selector_does_not_match_when_attribute_value_different(): void
    {
        $condition = new Condition(
            selector: 'attribute',
            selectorSubtype: 'plan',
            comparer: 'equal',
            values: [
                new ConditionValue('enterprise', ''),
                new ConditionValue('pro', ''),
            ]
        );

        $context = Context::fromArray([
            'type' => 'user',
            'identifier' => 'user-123',
            'name' => 'Test User',
            'attributes' => [
                ['key' => 'plan', 'values' => [['value' => 'free']]],
            ],
        ]);

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertFalse($result);
    }

    public function test_attribute_selector_returns_false_when_attribute_not_present(): void
    {
        $condition = new Condition(
            selector: 'attribute',
            selectorSubtype: 'plan',
            comparer: 'equal',
            values: [
                new ConditionValue('enterprise', ''),
            ]
        );

        $context = Context::single('user', 'user-123', 'Test User');

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertFalse($result);
    }

    public function test_attribute_selector_returns_false_when_selector_subtype_null(): void
    {
        $condition = new Condition(
            selector: 'attribute',
            selectorSubtype: null,
            comparer: 'equal',
            values: [
                new ConditionValue('value', ''),
            ]
        );

        $context = Context::fromArray([
            'type' => 'user',
            'identifier' => 'user-123',
            'name' => 'Test User',
        ]);

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertFalse($result);
    }

    public function test_attribute_selector_with_contains_operator(): void
    {
        $condition = new Condition(
            selector: 'attribute',
            selectorSubtype: 'email',
            comparer: 'contains',
            values: [
                new ConditionValue('@example.com', ''),
            ]
        );

        $context = Context::fromArray([
            'type' => 'user',
            'identifier' => 'user-123',
            'name' => 'Test User',
            'attributes' => [
                ['key' => 'email', 'values' => [['value' => 'user@example.com']]],
            ],
        ]);

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertTrue($result);
    }

    public function test_attribute_selector_with_multiple_attributes(): void
    {
        $condition = new Condition(
            selector: 'attribute',
            selectorSubtype: 'country',
            comparer: 'equal',
            values: [
                new ConditionValue('US', ''),
                new ConditionValue('CA', ''),
                new ConditionValue('UK', ''),
            ]
        );

        $context = Context::fromArray([
            'type' => 'user',
            'identifier' => 'user-123',
            'name' => 'Test User',
            'attributes' => [
                ['key' => 'country', 'values' => [['value' => 'CA']]],
                ['key' => 'plan', 'values' => [['value' => 'pro']]],
            ],
        ]);

        $result = $this->evaluator->evaluate($condition, $context);

        $this->assertTrue($result);
    }
}
