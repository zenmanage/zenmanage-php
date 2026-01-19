<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Zenmanage\Config\ConfigBuilder;
use Zenmanage\Flags\DefaultsCollection;
use Zenmanage\Flags\Context\Context;
use Zenmanage\Zenmanage;

/**
 * Defaults Usage
 * 
 * This example shows two ways to provide fallback values when a flag
 * is missing or unavailable:
 * 1) Inline default parameter to `single()`
 * 2) A `DefaultsCollection` applied via `withDefaults()`
 */

echo "=== Defaults Usage ===\n\n";

$zenmanage = new Zenmanage(
    ConfigBuilder::create()
        ->withEnvironmentToken('tok_your_environment_token_here')
        ->build()
);

// Example 1: Inline defaults on single() calls
// -------------------------------------------
echo "1. Inline Defaults\n\n";

$missingString = $zenmanage->flags()->single('nonexistent-string-flag', 'hello-world');
echo "   String default: " . $missingString->asString() . "\n";

$missingNumber = $zenmanage->flags()->single('nonexistent-number-flag', 42);
echo "   Number default: " . $missingNumber->asNumber() . "\n";

$missingBoolean = $zenmanage->flags()->single('nonexistent-boolean-flag', true);
echo "   Boolean default (isEnabled): " . ($missingBoolean->isEnabled() ? 'enabled' : 'disabled') . "\n\n";

// Example 2: DefaultsCollection applied to a FlagManager
// ------------------------------------------------------
echo "2. Defaults Collection\n\n";

$defaults = DefaultsCollection::fromArray([
    'fallback-theme' => 'dark',
    'max-items' => 100,
    'feature-x' => false,
]);

$flagsWithDefaults = $zenmanage->flags()->withDefaults($defaults);

$themeFlag = $flagsWithDefaults->single('fallback-theme');
echo "   Fallback theme: " . $themeFlag->asString() . "\n";

$maxItemsFlag = $flagsWithDefaults->single('max-items');
echo "   Max items: " . $maxItemsFlag->asNumber() . "\n";

$featureXFlag = $flagsWithDefaults->single('feature-x');
echo "   Feature X: " . ($featureXFlag->isEnabled() ? 'enabled' : 'disabled') . "\n\n";

// Example 3: Precedence — inline defaults override collection defaults
// --------------------------------------------------------------------
echo "3. Precedence (Inline > Collection)\n\n";

// Add a default in the collection
$defaults->set('priority-flag', 'from-collection');

// Using inline default should win over the collection value
$priorityInline = $zenmanage->flags()
    ->withDefaults($defaults)
    ->single('priority-flag', 'from-inline');

echo "   priority-flag with inline default: " . $priorityInline->asString() . "\n";

// Without inline default, collection value is used
$priorityFromCollection = $zenmanage->flags()
    ->withDefaults($defaults)
    ->single('priority-flag');

echo "   priority-flag from collection: " . $priorityFromCollection->asString() . "\n\n";

// Example 4: Defaults with context
// --------------------------------
echo "4. Defaults with Context\n\n";

$userContext = Context::single('user', 'user-101', 'Test User');

// Even with context, if the flag is missing, the defaults are used
$contextualFlag = $zenmanage->flags()
    ->withContext($userContext)
    ->withDefaults($defaults)
    ->single('feature-per-user');

echo "   feature-per-user for user-101: " . ($contextualFlag->isEnabled() ? 'enabled' : 'disabled') . "\n\n";

echo "Examples completed!\n";
