# Zenmanage PHP SDK

Add feature flags to your PHP application in minutes. Control feature rollouts, A/B test, and manage configurations without deploying code.

## Why Zenmanage?

- 🚀 **Fast**: Rules cached locally - ~1ms evaluation time
- 🎯 **Targeted**: Roll out features to specific users, organizations, or segments  
- 🛡️ **Safe**: Graceful fallbacks and error handling built-in
- 📊 **Insightful**: Automatic usage tracking (optional)
- 🧪 **Testable**: Easy to mock in tests

## Installation

```bash
composer require zenmanage/zenmanage-php
```

**Requirements**: PHP 8.0+

## Get Started in 60 Seconds

1. Get your environment token from [zenmanage.com](https://zenmanage.com)
2. Initialize the SDK:

```php
use Zenmanage\Config\ConfigBuilder;
use Zenmanage\Zenmanage;

$zenmanage = new Zenmanage(
    ConfigBuilder::create()
        ->withEnvironmentToken('tok_your_token_here')
        ->build()
);
```

3. Check a feature flag:

```php
if ($zenmanage->flags()->single('new-dashboard')->isEnabled()) {
    // Show new dashboard
    return view('dashboard-v2');
}

// Show old dashboard
return view('dashboard');
```

That's it! 🎉

## Common Use Cases

### Roll Out a New Feature Gradually

```php
// Check if user has access to beta features
$context = Context::single('user', $user->id, $user->name);

$betaAccess = $zenmanage->withContext($context)
    ->flags()
    ->single('beta-program')
    ->isEnabled();

if ($betaAccess) {
    // User is in beta program
    $features = $this->getBetaFeatures();
}
```

**Note:** Always call `withContext()` before `flags()` to ensure context is sent to the API when loading rules.

### A/B Testing

```php
$context = Context::fromArray([
    'type' => 'user',
    'identifier' => $user->id,
    'name' => $user->name,
    'attributes' => [
        ['key' => 'country', 'values' => [['value' => $user->country]]],
    ],
]);

$variant = $zenmanage->withContext($context)
    ->flags()
    ->single('checkout-flow')
    ->asString();

if ($variant === 'one-page') {
    return view('checkout.onepage');
} else {
    return view('checkout.multipage');
}
```

### Feature Toggles by Organization

```php
$context = Context::fromArray([
    'type' => 'organization',
    'identifier' => $user->organization->id,
    'name' => $user->organization->name,
    'attributes' => [
        ['key' => 'plan', 'values' => [['value' => $user->organization->plan]]],
    ],
]);

$advancedReports = $zenmanage->withContext($context)
    ->flags()
    ->single('advanced-reports')
    ->isEnabled();

if ($advancedReports) {
    return $this->getAdvancedReports();
}
```

### Configuration Values

```php
// Get configuration values from flags
$apiTimeout = $zenmanage->flags()
    ->single('api-timeout', 5000)  // Default 5000ms
    ->asNumber();

$maxUploadSize = $zenmanage->flags()
    ->single('max-upload-mb', 10)
    ->asNumber();

$welcomeMessage = $zenmanage->flags()
    ->single('welcome-text', 'Welcome!')
    ->asString();
```

### Kill Switch for Problem Features

```php
// Quickly disable a problematic feature via dashboard
if ($zenmanage->flags()->single('new-payment-processor', false)->isEnabled()) {
    return $this->processWithNewSystem($payment);
} else {
    return $this->processWithLegacySystem($payment);
}
```

## Setup for Your Application

### Laravel Integration

> [!TIP]
> There is an official Laravel integration on GitHub: [zenmanage/zenmanage-laravel](https://github.com/zenmanage/zenmanage-laravel). Use it to plug Zenmanage directly into your Laravel app with minimal setup.

Create a service provider to make Zenmanage available throughout your app:

```php
// app/Providers/ZenmanageServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Zenmanage\Config\ConfigBuilder;
use Zenmanage\Zenmanage;

class ZenmanageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Zenmanage::class, function ($app) {
            return new Zenmanage(
                ConfigBuilder::create()
                    ->withEnvironmentToken(config('services.zenmanage.token'))
                    ->withCacheBackend('filesystem')
                    ->withCacheDirectory(storage_path('framework/cache/zenmanage'))
                    ->withCacheTtl(3600)
                    ->build()
            );
        });
    }
}
```

```php
// config/services.php
return [
    // ...
    'zenmanage' => [
        'token' => env('ZENMANAGE_TOKEN'),
    ],
];
```

Then use dependency injection anywhere:

```php
class DashboardController extends Controller
{
    public function __construct(private Zenmanage $zenmanage) {}

    public function index(Request $request)
    {
        $context = Context::single('user', $request->user()->email);
        
        $useNewDashboard = $this->zenmanage->flags()
            ->withContext($context)
            ->single('new-dashboard', false)
            ->isEnabled();

        return $useNewDashboard 
            ? view('dashboard-v2')
            : view('dashboard');
    }
}
```

### Symfony Integration

```yaml
# config/services.yaml
services:
    Zenmanage\Zenmanage:
        factory: ['App\Factory\ZenmanageFactory', 'create']
        arguments:
            $token: '%env(ZENMANAGE_TOKEN)%'
            $cacheDir: '%kernel.cache_dir%/zenmanage'
```

```php
// src/Factory/ZenmanageFactory.php
namespace App\Factory;

use Zenmanage\Config\ConfigBuilder;
use Zenmanage\Zenmanage;

class ZenmanageFactory
{
    public static function create(string $token, string $cacheDir): Zenmanage
    {
        return new Zenmanage(
            ConfigBuilder::create()
                ->withEnvironmentToken($token)
                ->withCacheBackend('filesystem')
                ->withCacheDirectory($cacheDir)
                ->build()
        );
    }
}
```

### Standalone PHP Application

```php
// bootstrap.php or similar
$zenmanage = new Zenmanage(
    ConfigBuilder::create()
        ->withEnvironmentToken($_ENV['ZENMANAGE_TOKEN'])
        ->withCacheBackend('filesystem')
        ->withCacheDirectory(__DIR__ . '/cache/zenmanage')
        ->build()
);

// Make available globally (if needed)
$GLOBALS['zenmanage'] = $zenmanage;
// Or use a registry pattern, DI container, etc.
```

## Working with Contexts

Contexts let you target flags to specific users, organizations, or any custom attributes. This is how you do gradual rollouts, A/B tests, and targeted features.

### Simple Context (One Attribute)

```php
use Zenmanage\Flags\Context\Context;

// Target by user ID with name
$context = Context::single('user', $user->id, $user->name);

// Target by organization
$context = Context::single('organization', $company->id, $company->name);

// Target by user with just ID
$context = Context::single('user', $user->id);
```

### Rich Context (Multiple Attributes)

```php
$context = Context::fromArray([
    'type' => 'user',
    'identifier' => $user->id,
    'name' => $user->name,
    'attributes' => [
        ['key' => 'organization', 'values' => [['value' => $user->company->slug]]],
        ['key' => 'plan', 'values' => [['value' => $user->subscription->plan]]],
        ['key' => 'role', 'values' => [['value' => $user->role]]],
        ['key' => 'country', 'values' => [['value' => $user->country]]],
    ],
]);

$premiumFeatures = $zenmanage->flags()
    ->withContext($context)
    ->single('premium-dashboard')
    ->isEnabled();
```

**What you get:**
- `type`: Context type (user, organization, etc.)
- `identifier`: Unique identifier for targeting
- `name`: Human-readable display name
- `attributes`: Array of additional attributes for advanced targeting (plan, role, country, etc.)

**When to use contexts:**
- Rolling out to specific users (beta testers)
- Organization-based features (enterprise vs. free)
- Regional features (different countries)
- Role-based access (admins, moderators)
- Plan-based features (pro vs. basic)

### How Rule Evaluation Works

The SDK supports three types of rule selectors for targeting:

#### 1. Segment Selector

Matches against a list of specific context identifiers:

```php
// Rule: "Block these specific IPs"
// Selector: "segment", Values: [{"type": "user", "identifier": "140.248.31.37"}]

$context = Context::single('user', '140.248.31.37', 'Blocked User');
$result = $zenmanage->flags()
    ->withContext($context)
    ->single('allow-feature', true)
    ->isEnabled(); // Returns: false (matched segment)
```

#### 2. Context Selector

Same as segment - matches against context type and identifier:

```php
// Rule: "Enable for specific users"
// Selector: "context", Values: [{"type": "user", "identifier": "john-doe"}]

$context = Context::single('user', 'john-doe', 'John Doe');
$result = $zenmanage->flags()
    ->withContext($context)
    ->single('beta-feature', false)
    ->isEnabled(); // Returns: true (matched context)
```

#### 3. Attribute Selector

Matches against additional context attributes (plan, country, role, etc.):

```php
// Rule: "Enable for enterprise plans"
// Selector: "attribute", Subtype: "plan", Values: ["enterprise"]

$context = Context::fromArray([
    'type' => 'user',
    'identifier' => 'user-123',
    'name' => 'Jane Doe',
    'attributes' => [
        ['key' => 'plan', 'values' => [['value' => 'enterprise']]],
        ['key' => 'country', 'values' => [['value' => 'US']]],
    ],
]);

$result = $zenmanage->flags()
    ->withContext($context)
    ->single('premium-features', false)
    ->isEnabled(); // Returns: true (plan matched)
```

**Supported operators for all selectors:**
- `equal` - Exact match (most common)
- `contains` - Value contains the comparison string
- `starts_with` - Value starts with the comparison string
- `ends_with` - Value ends with the comparison string
- `regex` - Value matches regex pattern
- `greater_than`, `less_than`, `in`, etc. (see OperatorEvaluator for full list)

## Safe Defaults - Never Break Your App

Always provide defaults for critical features. The SDK will use them if:
- Flag doesn't exist yet
- API is unreachable
- Network issues occur

### Inline Defaults (Recommended)

```php
// If 'new-checkout' doesn't exist, returns true
$enabled = $zenmanage->flags()
    ->single('new-checkout', true)
    ->isEnabled();

// Configuration value with fallback
$timeout = $zenmanage->flags()
    ->single('api-timeout', 5000)
    ->asNumber();
```

### Default Collections (For Multiple Flags)

```php
use Zenmanage\Flags\DefaultsCollection;

$defaults = DefaultsCollection::fromArray([
    'new-ui' => true,
    'max-upload-size' => 10,
    'welcome-message' => 'Welcome to our app!',
    'feature-x' => false,
]);

$flags = $zenmanage->flags()->withDefaults($defaults);

// All these will use defaults if flags don't exist
$newUI = $flags->single('new-ui')->isEnabled();
$maxSize = $flags->single('max-upload-size')->asNumber();
$message = $flags->single('welcome-message')->asString();
```

### Priority Order

When retrieving a flag, the SDK checks in this order:

1. **API Value** - If flag exists in Zenmanage
2. **Inline Default** - Value passed to `single('flag', default)`
3. **Collection Default** - From `DefaultsCollection`
4. **Exception** - If none of the above

```php
$defaults = DefaultsCollection::fromArray(['timeout' => 3000]);

// Uses API value if exists, otherwise inline (5000), then collection (3000)
$timeout = $zenmanage->flags()
    ->withDefaults($defaults)
    ->single('timeout', 5000)
    ->asNumber();
```

## Performance - Caching Rules

The SDK caches flag rules to minimize API calls. Rules are fetched once, then served from cache.

### Default Setup (In-Memory)

Out of the box, flags are cached in memory for the request duration. Zero configuration needed:

```php
// First call fetches from API
$newUI = $zenmanage->flags()->single('new-ui')->isEnabled();

// Subsequent calls use memory cache (same request)
$newUI2 = $zenmanage->flags()->single('new-ui')->isEnabled(); // Instant
```

**Good for:** Most web applications, simple scripts

### File System Cache (Persist Between Requests)

Cache rules to disk for faster performance across multiple requests:

```php
$config = ConfigBuilder::create()
    ->withEnvironmentToken('tok_your_token')
    ->withCacheBackend('filesystem')
    ->withCacheDirectory('/var/cache/zenmanage')
    ->withCacheTtl(300) // 5 minutes
    ->build();
```

**Good for:** High-traffic sites, long-running processes, CLI applications

**When to use:**
- Production websites (cache between page loads)
- Background jobs (avoid repeated API calls)
- CLI tools (faster execution)

### Cache Duration

Control how long rules are cached:

```php
// 5 minutes (good for rapid development)
->withCacheTtl(300)

// 1 hour (good for production)
->withCacheTtl(3600)

// 1 day (good for stable flags)
->withCacheTtl(86400)
```

**Recommendation:** Start with 5-10 minutes in production. Increase once flags are stable.

### Disable Cache (Testing Only)

```php
// Always fetch fresh from API
$config = ConfigBuilder::create()
    ->withCacheBackend('null')
    ->build();
```

### Manually Refresh Rules

Force a fresh fetch from the API:

```php
$zenmanage->flags()->refreshRules();
```

## Logging & Debugging

Get visibility into what the SDK is doing by providing a PSR-3 logger:

```php
use Psr\Log\LoggerInterface;

$config = ConfigBuilder::create()
    ->withEnvironmentToken('tok_your_token')
    ->withLogger($yourLogger)
    ->build();
```

**What gets logged:**
- API requests and responses
- Cache hits and misses
- Rule evaluation results
- Errors and exceptions

**Example with Monolog:**

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('zenmanage');
$logger->pushHandler(new StreamHandler('path/to/zenmanage.log', Logger::DEBUG));

$config = ConfigBuilder::create()
    ->withLogger($logger)
    ->build();
```

## Error Handling in Production

The SDK is designed for graceful degradation. Your app should never break because of feature flags.

### Always Use Defaults for Critical Features

```php
// Bad - will throw exception if flag doesn't exist
$showNewUI = $zenmanage->flags()->single('new-ui')->isEnabled();

// Good - falls back to false if anything goes wrong
$showNewUI = $zenmanage->flags()->single('new-ui', false)->isEnabled();
```

### Handle API Failures

If the API is unreachable, the SDK will:
1. Use cached rules (if available)
2. Fall back to default values (if provided)
3. Throw exception (if no defaults)

**Recommended pattern:**

```php
try {
    $premiumEnabled = $zenmanage->flags()
        ->withContext($context)
        ->single('premium-features', false) // Default to false
        ->isEnabled();
        
    if ($premiumEnabled) {
        return $this->showPremiumDashboard();
    }
} catch (ZenmanageException $e) {
    // Log error but continue with default behavior
    $this->logger->warning('Flag check failed', ['error' => $e->getMessage()]);
    $premiumEnabled = false;
}

return $this->showStandardDashboard();
```

### Retry Logic

The SDK automatically retries failed API calls (3 attempts with exponential backoff). You don't need to handle this.

## Testing Your Feature Flags

Test your feature-flagged code without hitting the Zenmanage API.

### Use Defaults in Tests

```php
public function test_premium_users_see_dashboard()
{
    $zenmanage = new Zenmanage(
        ConfigBuilder::create()
            ->withEnvironmentToken('test-token')
            ->withCacheBackend('null') // No caching in tests
            ->build()
    );
    
    $defaults = DefaultsCollection::fromArray([
        'premium-dashboard' => true,
    ]);
    
    $enabled = $zenmanage->flags()
        ->withDefaults($defaults)
        ->single('premium-dashboard')
        ->isEnabled();
        
    $this->assertTrue($enabled);
}
```

### Mock the Flag Manager

```php
use PHPUnit\Framework\TestCase;
use Zenmanage\Flags\FlagManagerInterface;
use Zenmanage\Flags\Flag;

class CheckoutTest extends TestCase
{
    public function test_new_checkout_flow()
    {
        $flagManager = $this->createMock(FlagManagerInterface::class);
        $flagManager->method('single')
            ->willReturn(new Flag('new-checkout', 'New Checkout', true));
            
        $checkout = new CheckoutService($flagManager);
        
        $result = $checkout->processPayment($order);
        
        $this->assertTrue($result->usedNewFlow());
    }
}
```

### Test Different Flag States

```php
public function test_feature_disabled_shows_old_ui()
{
    $defaults = DefaultsCollection::fromArray(['new-ui' => false]);
    
    $flag = $this->zenmanage->flags()
        ->withDefaults($defaults)
        ->single('new-ui');
        
    $this->assertFalse($flag->isEnabled());
}

public function test_feature_enabled_shows_new_ui()
{
    $defaults = DefaultsCollection::fromArray(['new-ui' => true]);
    
    $flag = $this->zenmanage->flags()
        ->withDefaults($defaults)
        ->single('new-ui');
        
    $this->assertTrue($flag->isEnabled());
}
```

## Requirements

- PHP 8.0 or higher
- Composer
- Guzzle HTTP client (automatically installed)

## Installation

```bash
composer require zenmanage/zenmanage-php
```

## Development

Run tests:

```bash
composer test
```

Run static analysis:

```bash
composer phpstan
```

## License

MIT

## Support

- Documentation: https://github.com/zenmanage/zenmanage-php
- Issues: https://github.com/zenmanage/zenmanage-php/issues
- Email: hello@zenmanage.com
