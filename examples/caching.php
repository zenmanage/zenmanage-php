<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Zenmanage\Config\ConfigBuilder;
use Zenmanage\Zenmanage;

/**
 * Caching Setup Examples
 *
 * Demonstrates how to configure cache backends and TTL:
 * - Memory cache (default)
 * - Filesystem cache (with directory)
 * - Null cache (disabled)
 * - Environment-based configuration
 */

echo "=== Caching Setup Examples ===\n\n";

$token = 'tok_your_environment_token_here';

// Example 1: Memory cache (default)
// ---------------------------------
echo "1. Memory Cache (default)\n\n";

$zenmanageMemory = new Zenmanage(
    ConfigBuilder::create()
        ->withEnvironmentToken($token)
        ->withCacheBackend('memory') // default
        ->withCacheTtl(600) // 10 minutes
        ->build()
);

$allFlags = $zenmanageMemory->flags()->all();
echo "   Loaded " . count($allFlags) . " flags using in-memory cache (TTL 600s).\n\n";

// Example 2: Filesystem cache
// ---------------------------
echo "2. Filesystem Cache\n\n";

$cacheDir = sys_get_temp_dir() . '/zenmanage-cache';
$zenmanageFs = new Zenmanage(
    ConfigBuilder::create()
        ->withEnvironmentToken($token)
        ->withCacheBackend('filesystem')
        ->withCacheDirectory($cacheDir)
        ->withCacheTtl(3600) // 1 hour
        ->build()
);

$fsFlags = $zenmanageFs->flags()->all();
$files = glob($cacheDir . '/*.cache');
$filesCount = is_array($files) ? count($files) : 0;

echo "   Cache directory: {$cacheDir}\n";
echo "   Loaded " . count($fsFlags) . " flags and found {$filesCount} cache file(s).\n\n";

// Example 3: Null cache (disable caching)
// ---------------------------------------
echo "3. Null Cache (disabled)\n\n";

$zenmanageNull = new Zenmanage(
    ConfigBuilder::create()
        ->withEnvironmentToken($token)
        ->withCacheBackend('null')
        ->build()
);

// Force a refresh to illustrate direct API fetch
$zenmanageNull->flags()->refreshRules();
$nullFlags = $zenmanageNull->flags()->all();

echo "   Loaded " . count($nullFlags) . " flags with caching disabled.\n\n";

// Example 4: From environment variables
// -------------------------------------
echo "4. Environment-Based Configuration\n\n";

// Demonstration only: set env vars at runtime
putenv('ZENMANAGE_ENVIRONMENT_TOKEN=' . $token);
putenv('ZENMANAGE_CACHE_BACKEND=filesystem');
putenv('ZENMANAGE_CACHE_TTL=120');
putenv('ZENMANAGE_CACHE_DIR=' . $cacheDir);

$zenmanageEnv = new Zenmanage(
    ConfigBuilder::fromEnvironment()->build()
);

$envFlags = $zenmanageEnv->flags()->all();
$envFiles = glob($cacheDir . '/*.cache');
$envFilesCount = is_array($envFiles) ? count($envFiles) : 0;

echo "   Read config from ZENMANAGE_* environment variables.\n";
echo "   Loaded " . count($envFlags) . " flags; cache files present: {$envFilesCount}.\n\n";

echo "Examples completed!\n";
