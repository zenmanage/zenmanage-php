# Upgrading Zenmanage PHP SDK

This guide covers notable changes between releases.

## 3.0.0 to 3.1.0

The changes below apply when upgrading from 3.0.0 to 3.1.0.

## Usage Reporting Default Changed

### What changed

Usage reporting is now enabled by default. Previously, usage reporting was disabled unless you called `enableUsageReporting()` or set the environment variable.

### Why this matters

If you upgrade without changes, the SDK will now send usage-reporting requests to the `/v1/flags/{key}/usage` endpoint when `reportUsage()` is called (for example, when using `single()`).

### How to keep the old behavior (disabled by default)

If you want to keep usage reporting disabled, explicitly disable it in your configuration:

```php
use Zenmanage\Config\ConfigBuilder;

$config = ConfigBuilder::create()
    ->withEnvironmentToken('tok_your_token_here')
    ->withUsageReporting(false)
    ->build();
```

### New API: withUsageReporting(bool)

Use `withUsageReporting(true|false)` to control usage reporting. The old `enableUsageReporting()` and `disableUsageReporting()` methods are deprecated and will be removed in a future release.

### Environment variable

`ZENMANAGE_ENABLE_USAGE_REPORTING` still works and now overrides the default:

- `true` or `1` enables usage reporting
- `false` or `0` disables usage reporting

## Deprecated methods

- `ConfigBuilder::enableUsageReporting()`
- `ConfigBuilder::disableUsageReporting()`

Use `ConfigBuilder::withUsageReporting(bool)` instead.
