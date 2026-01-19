<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Zenmanage\Config\ConfigBuilder;
use Zenmanage\Flags\Context\Attribute;
use Zenmanage\Flags\Context\Context;
use Zenmanage\Zenmanage;

/**
 * Context-Based Flag Evaluation
 * 
 * This example demonstrates how to use contexts to evaluate flags with rules.
 * Contexts allow you to pass information about the user, organization, or other
 * entities that influence flag evaluation based on server-side rules.
 */

echo "=== Context-Based Flag Evaluation ===\n\n";

$zenmanage = new Zenmanage(
    ConfigBuilder::create()
        ->withEnvironmentToken('tok_your_environment_token_here')
        ->build()
);

// Example 1: Simple user context
echo "1. Simple User Context\n\n";

$userContext = Context::single('user', 'user-12345', 'John Doe');

echo "   Context Type: {$userContext->getType()}\n";
echo "   Context Identifier: {$userContext->getIdentifier()}\n";
echo "   Context Name: {$userContext->getName()}\n\n";

$flagWithContext = $zenmanage->flags()
    ->withContext($userContext)
    ->single('feature-new-ui');

echo "   Flag 'feature-new-ui' for user: " . ($flagWithContext->isEnabled() ? 'enabled' : 'disabled') . "\n\n";

// Example 2: Organization context
echo "2. Organization Context\n\n";

$orgContext = Context::single('organization', 'org-acme-corp', 'Acme Corporation');

echo "   Context Type: {$orgContext->getType()}\n";
echo "   Context Identifier: {$orgContext->getIdentifier()}\n";
echo "   Context Name: {$orgContext->getName()}\n\n";

$flagForOrg = $zenmanage->flags()
    ->withContext($orgContext)
    ->single('enterprise-feature');

echo "   Flag 'enterprise-feature' for org: " . ($flagForOrg->isEnabled() ? 'enabled' : 'disabled') . "\n\n";

// Example 3: Context with attributes
echo "3. User Context with Custom Attributes\n\n";

$userWithAttrs = new Context(
    type: 'user',
    name: 'Jane Smith',
    identifier: 'user-98765',
    attributes: [
        new Attribute('subscription_plan', ['premium', 'annual']),
        new Attribute('country', ['US']),
    ]
);

echo "   User: {$userWithAttrs->getName()}\n";
echo "   Subscription Plan: " . implode(', ', $userWithAttrs->getAttribute('subscription_plan')->getValues()) . "\n";
echo "   Country: " . implode(', ', $userWithAttrs->getAttribute('country')->getValues()) . "\n\n";

$premiumFlag = $zenmanage->flags()
    ->withContext($userWithAttrs)
    ->single('premium-feature');

echo "   Flag 'premium-feature' for premium user: " . ($premiumFlag->isEnabled() ? 'enabled' : 'disabled') . "\n\n";

// Example 4: Building context from array
echo "4. Context from Array (JSON Data)\n\n";

$contextData = [
    'type' => 'user',
    'name' => 'Bob Johnson',
    'identifier' => 'user-54321',
    'attributes' => [
        ['key' => 'plan', 'values' => [['value' => 'starter']]],
        ['key' => 'beta_tester', 'values' => [['value' => 'true']]],
        ['key' => 'region', 'values' => [['value' => 'eu-west']]],
    ],
];

$contextFromArray = Context::fromArray($contextData);

echo "   User: {$contextFromArray->getName()}\n";
echo "   Plan: " . implode(', ', $contextFromArray->getAttribute('plan')->getValues()) . "\n";
echo "   Beta Tester: " . implode(', ', $contextFromArray->getAttribute('beta_tester')->getValues()) . "\n";
echo "   Region: " . implode(', ', $contextFromArray->getAttribute('region')->getValues()) . "\n\n";

$betaFlag = $zenmanage->flags()
    ->withContext($contextFromArray)
    ->single('beta-features');

echo "   Flag 'beta-features' for beta tester: " . ($betaFlag->isEnabled() ? 'enabled' : 'disabled') . "\n\n";

// Example 5: Adding attributes dynamically
echo "5. Dynamically Adding Attributes\n\n";

$dynamicContext = Context::single('user', 'user-11111', 'Alice Wonder');

echo "   Initial attributes: " . count($dynamicContext->getAttributes()) . "\n";

$dynamicContext->addAttribute(new Attribute('role', ['admin', 'moderator']));
$dynamicContext->addAttribute(new Attribute('created_at', ['2025-01-01']));

echo "   After adding attributes: " . count($dynamicContext->getAttributes()) . "\n";
echo "   Role: " . implode(', ', $dynamicContext->getAttribute('role')->getValues()) . "\n";
echo "   Created At: " . implode(', ', $dynamicContext->getAttribute('created_at')->getValues()) . "\n\n";

$adminFlag = $zenmanage->flags()
    ->withContext($dynamicContext)
    ->single('admin-panel');

echo "   Flag 'admin-panel' for admin: " . ($adminFlag->isEnabled() ? 'enabled' : 'disabled') . "\n\n";

// Example 6: Context serialization
echo "6. Context JSON Serialization\n\n";

$serializeContext = new Context(
    type: 'user',
    name: 'Charlie Brown',
    identifier: 'user-99999',
    attributes: [
        new Attribute('department', ['engineering']),
    ]
);

$serialized = $serializeContext->jsonSerialize();
echo "   JSON: " . json_encode($serialized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Example 7: Multiple flags with same context
echo "7. Multiple Flag Evaluations with Same Context\n\n";

$masterContext = new Context(
    type: 'user',
    name: 'Diana Prince',
    identifier: 'user-77777',
    attributes: [
        new Attribute('account_tier', ['gold']),
        new Attribute('feature_early_access', ['true']),
    ]
);

$flagsWithContext = $zenmanage->flags()->withContext($masterContext);

$featureA = $flagsWithContext->single('feature-a');
$featureB = $flagsWithContext->single('feature-b');
$featureC = $flagsWithContext->single('feature-c');

echo "   Feature A: " . ($featureA->isEnabled() ? 'enabled' : 'disabled') . "\n";
echo "   Feature B: " . ($featureB->isEnabled() ? 'enabled' : 'disabled') . "\n";
echo "   Feature C: " . ($featureC->isEnabled() ? 'enabled' : 'disabled') . "\n\n";

// Example 8: Service context
echo "8. Service Context (Non-User)\n\n";

$serviceContext = Context::single('service', 'svc-scheduler', 'Background Job Scheduler');

echo "   Service Type: {$serviceContext->getType()}\n";
echo "   Service Name: {$serviceContext->getName()}\n\n";

$serviceFlag = $zenmanage->flags()
    ->withContext($serviceContext)
    ->single('enable-async-processing');

echo "   Flag 'enable-async-processing' for service: " . ($serviceFlag->isEnabled() ? 'enabled' : 'disabled') . "\n\n";

echo "Examples completed!\n";
