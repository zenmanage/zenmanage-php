<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Zenmanage\Config\ConfigBuilder;
use Zenmanage\Flags\Context\Attribute;
use Zenmanage\Flags\Context\Context;
use Zenmanage\Zenmanage;

/**
 * A/B Testing with Flags + Context
 *
 * Demonstrates how to evaluate a string variant flag using user context.
 * Includes a simple deterministic bucketing strategy to assign users to
 * buckets client-side and send that as a context attribute for server rules.
 */

echo "=== A/B Testing Example ===\n\n";

$zenmanage = new Zenmanage(
    ConfigBuilder::create()
        ->withEnvironmentToken('tok_your_environment_token_here')
        ->withCacheTtl(600)
        ->build()
);

/**
 * Compute a deterministic bucket (0-99) from a stable identifier.
 * This is useful when your server-side rules target ranges of buckets.
 */
function abBucket(string $identifier): int
{
    // crc32 returns unsigned int; modulo 100 for 100-way split
    $hash = crc32($identifier);
    return (int) ($hash % 100);
}

/**
 * Evaluate a variant flag for a given user.
 */
function evaluateVariant(Zenmanage $zenmanage, string $userId, string $userName): void
{
    $bucket = abBucket($userId);

    $context = new Context(
        type: 'user',
        name: $userName,
        identifier: $userId,
        attributes: [
            // Send bucket so server-side rules can target ranges, e.g., 0-49 vs 50-99
            new Attribute('ab_bucket', [(string) $bucket]),
            // Additional targeting signals if you need (country, plan, etc.)
            // new Attribute('country', ['US']),
        ]
    );

    $variantFlag = $zenmanage->flags()
        ->withContext($context)
        // Inline default ensures safe fallback if the flag is not configured yet
        ->single('landing-page-variant', 'control');

    $variant = $variantFlag->asString();

    echo "User {$userId} ({$userName})\n";
    echo "   Bucket: {$bucket}\n";
    echo "   Variant: {$variant}\n\n";
}

// Try a few users to see variant assignment remain deterministic per user
// (assuming server-side rules target ab_bucket ranges)
evaluateVariant($zenmanage, 'user-1001', 'Alice');
evaluateVariant($zenmanage, 'user-1002', 'Bob');
evaluateVariant($zenmanage, 'user-1003', 'Charlie');

echo "Examples completed!\n";
