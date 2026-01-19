<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Zenmanage\Config\ConfigBuilder;
use Zenmanage\Zenmanage;

/**
 * Simple Flag Operations
 * 
 * This example demonstrates basic flag retrieval and type-safe value access.
 */

echo "=== Simple Flag Operations ===\n\n";

$zenmanage = new Zenmanage(
    ConfigBuilder::create()
        ->withEnvironmentToken('tok_your_environment_token_here')
        ->build()
);

// Example 1: Boolean flags
echo "1. Boolean Flags\n\n";

$boolFlag = $zenmanage->flags()->single('example-boolean-flag');
echo "   Boolean Flag: " . ($boolFlag->asBool() ? 'true' : 'false') . "\n";

$enabledFlag = $zenmanage->flags()->single('example-feature-enabled');
echo "   Feature Enabled: " . ($enabledFlag->isEnabled() ? 'Yes' : 'No') . "\n\n";

// Example 2: String flags
echo "2. String Flags\n\n";

$stringFlag = $zenmanage->flags()->single('example-string-flag');
echo "   String Flag: {$stringFlag->asString()}\n";

$variantFlag = $zenmanage->flags()->single('example-variant-flag');
echo "   Variant Flag: {$variantFlag->asString()}\n\n";

// Example 3: Number flags
echo "3. Number Flags\n\n";

$numberFlag = $zenmanage->flags()->single('example-number-flag');
echo "   Number Flag: {$numberFlag->asNumber()}\n";

$limitFlag = $zenmanage->flags()->single('example-limit-flag');
echo "   Limit Flag: {$limitFlag->asNumber()}\n\n";

// Example 4: Get all flags
echo "4. Retrieving All Flags\n\n";

$flags = $zenmanage->flags()->all();
echo "   Total flags: " . count($flags) . "\n\n";

foreach ($flags as $flag) {
    echo "   - {$flag->getKey()} ({$flag->getType()}): ";
    
    if ($flag->getType() === 'boolean') {
        echo $flag->isEnabled() ? 'enabled' : 'disabled';
    } else {
        echo $flag->getValue();
    }
    
    echo "\n";
}

echo "\n=== Type-Safe Access ===\n\n";

$flag = $zenmanage->flags()->single('example-number-flag');

echo "   getValue(): {$flag->getValue()}\n";
echo "   asString(): {$flag->asString()}\n";
echo "   asNumber(): {$flag->asNumber()}\n";
echo "   asBool(): " . ($flag->asBool() ? 'true' : 'false') . "\n";
echo "   isEnabled(): " . ($flag->isEnabled() ? 'true' : 'false') . "\n\n";

echo "Examples completed!\n";
