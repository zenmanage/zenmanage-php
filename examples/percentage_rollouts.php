<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Zenmanage\Config\ConfigBuilder;
use Zenmanage\Flags\Context\Attribute;
use Zenmanage\Flags\Context\Context;
use Zenmanage\Zenmanage;

/**
 * Percentage Rollouts
 *
 * Demonstrates SDK-side percentage rollouts. When a flag includes a `rollout`
 * configuration, the SDK automatically buckets the context identifier via
 * CRC32B hashing and selects either the rollout target/rules or the fallback
 * target/rules.
 *
 * No manual bucketing is needed — the SDK handles it internally.
 */

echo "=== Percentage Rollouts Example ===\n\n";

$zenmanage = new Zenmanage(
    ConfigBuilder::create()
        ->withEnvironmentToken('tok_your_environment_token_here')
        ->withCacheTtl(600)
        ->build()
);

// 1. Basic rollout — the flag's rollout configuration determines who gets the new value
echo "1. Basic Rollout (Boolean Flag)\n\n";

$userIds = ['user-1', 'user-2', 'user-3', 'user-4', 'user-5'];

foreach ($userIds as $userId) {
    $context = Context::single('user', $userId);

    $flag = $zenmanage->flags()
        ->withContext($context)
        ->single('new-checkout-flow');

    $enabled = $flag->isEnabled();

    echo "   {$userId}: " . ($enabled ? 'IN rollout (new flow)' : 'NOT in rollout (old flow)') . "\n";
}

echo "\n";

// 2. Rollout with context attributes — rollout rules can further filter
echo "2. Rollout with Attribute Rules\n\n";

$users = [
    ['id' => 'user-100', 'country' => 'US'],
    ['id' => 'user-200', 'country' => 'GB'],
    ['id' => 'user-300', 'country' => 'US'],
];

foreach ($users as $user) {
    $context = new Context(
        type: 'user',
        identifier: $user['id'],
        attributes: [
            new Attribute('country', [$user['country']]),
        ]
    );

    $flag = $zenmanage->flags()
        ->withContext($context)
        ->single('premium-feature', false);

    echo "   {$user['id']} ({$user['country']}): " . ($flag->isEnabled() ? 'enabled' : 'disabled') . "\n";
}

echo "\n";

// 3. String variant rollout
echo "3. String Variant Rollout\n\n";

foreach (['user-alpha', 'user-beta', 'user-gamma'] as $userId) {
    $context = Context::single('user', $userId);

    $flag = $zenmanage->flags()
        ->withContext($context)
        ->single('landing-page-variant', 'control');

    echo "   {$userId}: {$flag->asString()}\n";
}

echo "\n";

// 4. Deterministic bucketing — same user always gets same result
echo "4. Deterministic Bucketing\n\n";

$context = Context::single('user', 'consistent-user');

$result1 = $zenmanage->flags()->withContext($context)->single('rollout-flag', false)->isEnabled();
$result2 = $zenmanage->flags()->withContext($context)->single('rollout-flag', false)->isEnabled();
$result3 = $zenmanage->flags()->withContext($context)->single('rollout-flag', false)->isEnabled();

echo '   Same result every time: ' . ($result1 === $result2 && $result2 === $result3 ? 'Yes' : 'No') . "\n\n";

echo "Examples completed!\n";
