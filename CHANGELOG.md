# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-01-15

### Added
- Complete rewrite of the PHP SDK with modern architecture
- Local rule evaluation with zero per-flag API calls
- Multiple cache backend support (in-memory, filesystem, null)
- Fluent configuration builder with environment variable support
- Context-based flag evaluation with first-class `identifier`, `name`, and `type` properties
- **`withContext()` method on main Zenmanage class** - Call before `flags()` to ensure context is sent to API when loading rules
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
- **Recommended pattern: `$zenmanage->withContext($context)->flags()`** instead of `$zenmanage->flags()->withContext($context)`
  - The old pattern still works (backwards compatible), but the new pattern ensures context is available when rules are loaded from the API
  - All examples updated to demonstrate the recommended pattern
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
