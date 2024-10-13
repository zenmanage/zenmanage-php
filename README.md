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
use \Zenmanage\Client;

$client = new Client::config(['environment_token' => 'tok_sample'])->connect();
```

## Context

When retrieving values for feature flags a context can be provided that can change the value based on unique attributes of the context.

```php
use \Zenmanage\Client;
use \Zenmanage\Flags\Request\Entities\Context\Attribute;
use \Zenmanage\Flags\Request\Entities\Context\Context;
use \Zenmanage\Flags\Request\Entities\Context\Value;

$context = new Context('user', 'John Doe', 'john-doe', [
    new Attribute('key', [
        new Value('value'),
    ]),
]);

$client = Client::config()
    ->withContext($context)
    ->connect();

$results = $client->all();
$result = $client->get('flag-key')->getValue();

```

## Usage

Before retrieving a feature flag, create a new client. If you configured your environment token key via environment variables there's nothing to add. Otherwise, see the example above.

```php
use \Zenmanage\Client;

$client = new Client();
```

### Retrieving Flags

#### All Flags

```php
$results = $client->all();

foreach ($results as $results) {
    $key = $result->key;
    $name = $result->name;
    $type = $result->type;
    $value = $result->value
}
```

#### Single Flag

```php
$result = $client->flag('flag-key');

$key = $result->key;
$name = $result->name;
$type = $result->type;
$value = $result->value
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
