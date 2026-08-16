# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- `FlagManager::all()` now falls back to the configured `DefaultsCollection` on a per-key basis — previously a `DefaultsCollection` set via `withDefaults()` was silently ignored by `all()`, so a total rule-loading failure returned an empty array instead of the configured defaults.

## [5.1.2] - 2026-08-05

### Fixed
- `FlagManager::single()` now reports the effective default value (inline parameter, falling back to a `DefaultsCollection` entry) on every usage report, including when the flag is found and evaluated normally — previously the default was only sent on the fallback paths.

## [5.1.1] - 2026-08-05

### Changed
- Renamed SDK request headers to use a consistent `X-ZEN-` prefix, matching the server-side convention:
  - `X-API-Key` → `X-ZEN-API-KEY`
  - `X-ZENMANAGE-CONTEXT` → `X-ZEN-CONTEXT`
  - `X-Default-Value` → `X-ZEN-DEFAULT-VALUE`
  - The API still accepts the old header names as legacy aliases, so this is non-breaking.

## [5.0.0] - 2026-05-29

### Changed
- Minimum PHP version raised to **8.1** — PHP 8.0 is no longer supported

### Removed
- PHP 8.0 support

## [2.0.0] - 2026-01-15

### Added
- Complete rewrite of the PHP SDK with modern architecture
- Local rule evaluation with zero per-flag API calls
- Multiple cache backend support (in-memory, filesystem, null)
- Fluent configuration builder with environment variable support
- Context-based flag evaluation with first-class `identifier`, `name`, and `type` properties
- Three rule selector types for advanced targeting:
  - **Segment selector** - Match specific context identifiers
  - **Context selector** - Same as segment (alternative name)
  - **Attribute selector** - Match additional context attributes (plan, country, role, etc.)
- Support for all comparison operators (equal, contains, starts_with, regex, etc.)
- Context tracking - automatically sends context to API when fetching rules for analytics
- PSR-3 compliant logging
- PSR-4 autoloading
- PSR-12 coding standards
- Full PHP 8.0+ type hints and strict typing
- Comprehensive exception hierarchy
- Rule evaluation engine with operator strategies
- API client with retry logic and exponential backoff
- Value objects for type-safe data handling
- Dependency injection throughout for testability
- Comprehensive test suite (57 tests, 108 assertions)
- Context and Attribute classes match API entity structure for seamless interoperability

### Changed
- Replaced per-flag API calls with single rules fetch
- Improved performance with local evaluation
- Enhanced type safety with strict typing
- Context now requires `type` parameter and supports optional `identifier` and `name` as first-class properties
- Context structure now matches API format:
  - Property renamed: `id` → `identifier`
  - Attributes now structured as `['key' => 'string', 'values' => ['value1', 'value2']]`
  - JSON serialization format updated to match API expectations
- Attribute class updated:
  - Properties renamed: `type` → `key`, `identifier` → `values` (array)
  - Now supports multiple values per attribute key
  - Matching logic updated to check all values

### Technical Details
- **Architecture**: Clean, SOLID-compliant design
- **Caching**: Configurable TTL and multiple backends
- **Evaluation**: Local rule engine with operator patterns
- **Testing**: Designed for easy mocking and testing
- **Documentation**: Comprehensive inline docs and examples
