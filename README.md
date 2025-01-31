# Zenmanage API SDK for PHP 

[![Build Status](https://github.com/zenmanage/zenmanage-php/actions/workflows/tests.yml/badge.svg)](https://github.com/zenmanage/zenmanage-php) [![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=zenmanage_zenmanage-php&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=zenmanage_zenmanage-php)

This library helps with integrating Zenmanage into PHP applications.

## Installation

This library can be installed via [Composer](https://getcomposer.org):

```bash
composer require zenmanage/zenmanage-php
```

## Configuration

The only required configuration is the Environment Token. You can get your Environment Token via the [Project settings](https://app.zenmanage.com/admin/projects) in your Zenmanage account.

Configuration values can be set when creating a new API client or via environment variables. The environment takes precedence over values provided during the initialization process.

**Configuration via environment variables**

```bash
ZENMANAGE_ENVIRONMENT_TOKEN=tok_sample
```

**Configuration during initialization**

```php
use \Zenmanage\Zenmanage;

$zenmanage = new Zenmanage(['environment_token' => 'tok_sample']);
```

## Context

When retrieving values for feature flags, a context can be provided that can change the value based on unique attributes of the context.

```php
use \Zenmanage\Zenmanage;
use \Zenmanage\Flags\Request\Entities\Context\Attribute;
use \Zenmanage\Flags\Request\Entities\Context\Context;
use \Zenmanage\Flags\Request\Entities\Context\Value;

$context = new Context('user', 'John Doe', 'id-123', [
    new Attribute('company', [
        new Value('JD, Inc.'),
    ]),
]);

$zenmanage = new Zenmanage()->flags
    ->withContext($context);
```

## Default Values

When retrieving values for feature flags, a default value will be returned to the application if the Zenmanage API is unavailable or responds incorrectly. This will ensure your app will still function in the event that a flag cannot be evaluated.

```php
use \Zenmanage\Zenmanage;

$zenmanage = new Zenmanage()->flags
    ->withDefault('flag-key', 'boolean', false);

```

## Retrieving a Feature Flag Value

Before retrieving a feature flag, create a new instance of Zenmanage. If you configured your environment token key via environment variables there's nothing to add. Otherwise, see the example above.

```php
use \Zenmanage\Zenmanage;

$zenmanage = new Zenmanage();
```

### Retrieving Flags

#### All Flags

```php
$results = $zenmanage->flags->all();

foreach ($results as $results) {
    $key = $result->key;
    $name = $result->name;
    $type = $result->type;
    $value = $result->value
}
```

#### Single Flag

```php
$result = $zenmanage->flags->single('flag-key');

$key = $result->key;
$name = $result->name;
$type = $result->type;
$value = $result->value
```

## Reporting Feature Flag Usage

When your application uses a feature flag, it can notify Zenmanage of the usage. This helps Zenmanage determine which flags are active and which may have been abandoned.

```php
$zenmanage->flags->report('flag-key');
```

## Contributing

Bug reports and pull requests are welcome on GitHub at https://github.com/zenmanage/zenmanage-php. This project is intended to be a safe, welcoming space for collaboration, and contributors are expected to adhere to the [Contributor Covenant](http://contributor-covenant.org) code of conduct.

## License

The library is available as open source under the terms of the [MIT License](http://opensource.org/licenses/MIT).

## Code of Conduct

Everyone interacting in the Zenmanage’s code bases, issue trackers, chat rooms and mailing lists is expected to follow the [code of conduct](https://github.com/zenmanage/zenmanage-php/blob/master/CODE_OF_CONDUCT.md).

## What is Zenmanage?

[Zenmanage](https://zenmanage.com/) allows you to control how feature flags are configured in your application giving you better flexibility to deploy code and release when you are ready.

Zenmanage was started in 2023 as an alternative to highly complex feature flag tools. Learn more [about us](https://zenmanage.com/).
