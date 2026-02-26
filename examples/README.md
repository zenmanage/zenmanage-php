# Zenmanage PHP SDK Examples

This folder contains small, self-contained scripts demonstrating common flag operations with the Zenmanage PHP SDK.

## Prerequisites
- Ensure dependencies are installed: `composer install` (run in `sdk/zenmanage-php`).
- Each example initializes `Zenmanage` with `ConfigBuilder::withEnvironmentToken(...)`. Replace the placeholder token (`tok_your_environment_token_here`) with a valid environment token before running.

## How to Run
From the `sdk/zenmanage-php` directory:

```bash
php examples/simple_flags.php
php examples/context_based_flags.php
php examples/defaults.php
php examples/caching.php
php examples/ab_testing.php
php examples/percentage_rollouts.php
```

## Examples

### 1) simple_flags.php
Demonstrates basic flag retrieval and type-safe access.
- Fetch single flags (boolean, string, number) and read values.
- Check `isEnabled()` for boolean flags.
- Retrieve all flags and iterate their keys/types/values.
- Show type-safe methods: `getValue()`, `asString()`, `asNumber()`, `asBool()`.

### 2) context_based_flags.php
Shows how to evaluate flags with a `Context` to apply server-side rules.
- Create simple contexts (e.g., `user`, `organization`, `service`).
- Attach custom attributes via `Attribute` for rule matching.
- Build a context from array/JSON (`Context::fromArray`).
- Dynamically add attributes with `addAttribute`.
- Serialize context to JSON for inspection.
- Reuse the same context for multiple flags.

### 3) defaults.php
Illustrates safe fallbacks when a flag is missing or unavailable.
- Provide an inline default: `flags()->single('missing-key', $defaultValue)`.
- Apply a reusable `DefaultsCollection` via `withDefaults()`.
- Precedence: inline defaults override collection defaults.
- Use defaults together with a `Context`.

### 4) caching.php
Configures cache behavior for flag rules.
- Memory cache (default) with custom TTL via `withCacheTtl()`.
- Filesystem cache via `withCacheBackend('filesystem')` and `withCacheDirectory()`.
- Null cache to disable caching via `withCacheBackend('null')`.
- Environment-based configuration using `ConfigBuilder::fromEnvironment()` (supports `ZENMANAGE_ENVIRONMENT_TOKEN`, `ZENMANAGE_CACHE_BACKEND`, `ZENMANAGE_CACHE_TTL`, `ZENMANAGE_CACHE_DIR`).

### 5) ab_testing.php
Demonstrates A/B testing using a string variant flag with context.
- Create a `user` context and add a deterministic `ab_bucket` attribute.
- Evaluate a variant flag (e.g., `landing-page-variant`) via `asString()`.
- Use an inline default (e.g., `control`) to avoid errors if not yet configured.
- Server-side rules can target bucket ranges (e.g., 0–49 for `A`, 50–99 for `B`).

### 6) percentage_rollouts.php
Demonstrates SDK-side percentage rollouts with automatic CRC32B bucketing.
- The SDK buckets each context identifier deterministically — no manual hashing needed.
- Basic boolean rollout: users are included or excluded based on their bucket.
- Rollout with attribute rules: further filter within the rollout group (e.g., country).
- String variant rollout: serve different string values to rollout vs. fallback.
- Deterministic bucketing: same user always gets the same result.

## Notes
- Output is printed to the console to make behavior easy to observe.
- If a flag key does not exist and no default is provided, an evaluation exception is thrown; the defaults example shows how to avoid this.
