# appsbd/auth v1.0 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the `appsbd/auth` Composer package: Google OAuth2 + Cloudflare Turnstile services for Laravel, per `docs/superpowers/specs/2026-07-21-appsbd-auth-design.md`.

**Architecture:** Services-only package (no routes/controllers). Hand-rolled OAuth on Laravel HTTP Client. Services take a config array in the constructor; the service provider builds them as singletons from `config/appsbd-auth.php`. Typed readonly DTOs, events for app integration, exceptions for all failure paths.

**Tech Stack:** PHP ^8.3, illuminate/* ^12.0|^13.0, Pest, Orchestra Testbench.

## Global Constraints

- Package name `appsbd/auth`, namespace `Appsbd\Auth`, PSR-4 from `src/`, MIT license.
- PHP `^8.3`; `illuminate/support|http|validation|session|contracts` `^12.0|^13.0`.
- **No Socialite. No routes, no controllers, no views, no npm package, no User-model access.**
- Env var names exactly: `APPSBD_GOOGLE_CLIENT_ID`, `APPSBD_GOOGLE_CLIENT_SECRET`, `APPSBD_GOOGLE_REDIRECT_URI`, `APPSBD_TURNSTILE_SITE_KEY`, `APPSBD_TURNSTILE_SECRET`, `APPSBD_TURNSTILE_TIMEOUT`.
- Config publish tag: `appsbd-auth-config`. Middleware alias: `turnstile`. String validation rule: `turnstile`.
- Missing/empty required config throws `ConfigurationException` naming the full key (e.g. `appsbd-auth.google.client_id`).
- `TurnstileService::verify()` never throws on network failure — returns failed `CaptchaResponse` with `internal-error`.
- All Google HTTP errors map to `OAuthException`; raw client exceptions never leak.
- Every task ends with `composer test` passing. Commit after every task. **No Co-Authored-By / AI-attribution trailers in commits.**
- Run all commands from repo root `d:\WebProjects\projects\appsbd-auth`.

---

### Task 1: Package skeleton + test harness

**Files:**
- Create: `composer.json`, `.gitignore`, `LICENSE`, `phpunit.xml`, `src/Providers/AuthServiceProvider.php` (empty shell), `tests/TestCase.php`, `tests/Pest.php`

**Interfaces:**
- Produces: `Appsbd\Auth\Tests\TestCase` (extends Testbench, registers `AuthServiceProvider`); `composer test` script running Pest.

- [ ] **Step 1: Write `composer.json`**

```json
{
    "name": "appsbd/auth",
    "description": "Authentication integrations for Appsbd Laravel products: Google OAuth2 and Cloudflare Turnstile.",
    "type": "library",
    "license": "MIT",
    "keywords": ["laravel", "authentication", "oauth", "google", "turnstile", "captcha"],
    "authors": [
        { "name": "Appsbd" }
    ],
    "require": {
        "php": "^8.3",
        "illuminate/support": "^12.0|^13.0",
        "illuminate/http": "^12.0|^13.0",
        "illuminate/validation": "^12.0|^13.0",
        "illuminate/session": "^12.0|^13.0",
        "illuminate/contracts": "^12.0|^13.0"
    },
    "require-dev": {
        "pestphp/pest": "^3.0|^4.0",
        "orchestra/testbench": "^10.0|^11.0"
    },
    "autoload": {
        "psr-4": { "Appsbd\\Auth\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "Appsbd\\Auth\\Tests\\": "tests/" }
    },
    "extra": {
        "laravel": {
            "providers": ["Appsbd\\Auth\\Providers\\AuthServiceProvider"],
            "aliases": {
                "GoogleOAuth": "Appsbd\\Auth\\Facades\\GoogleOAuth",
                "Turnstile": "Appsbd\\Auth\\Facades\\Turnstile"
            }
        }
    },
    "scripts": {
        "test": "pest"
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": { "pestphp/pest-plugin": true }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

- [ ] **Step 2: Write `.gitignore`, `LICENSE`, `phpunit.xml`**

`.gitignore`:
```
/vendor/
composer.lock
.phpunit.result.cache
.phpunit.cache/
```

`LICENSE`: standard MIT text, copyright line `Copyright (c) 2026 Appsbd`.

`phpunit.xml`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Package">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Write empty provider `src/Providers/AuthServiceProvider.php`**

```php
<?php

namespace Appsbd\Auth\Providers;

use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
```

- [ ] **Step 4: Write `tests/TestCase.php` and `tests/Pest.php`**

`tests/TestCase.php`:
```php
<?php

namespace Appsbd\Auth\Tests;

use Appsbd\Auth\Providers\AuthServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [AuthServiceProvider::class];
    }
}
```

`tests/Pest.php`:
```php
<?php

uses(Appsbd\Auth\Tests\TestCase::class)->in(__DIR__);
```

- [ ] **Step 5: Install and verify harness runs**

Run: `composer install` then `composer test`
Expected: install succeeds; Pest reports no tests (exit 0). If Pest errors on zero tests, add a placeholder `tests/SmokeTest.php`:
```php
<?php

it('boots the package service provider', function () {
    expect($this->app->getProviders(\Appsbd\Auth\Providers\AuthServiceProvider::class))->not->toBeEmpty();
});
```
Run `composer test` again. Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add composer.json .gitignore LICENSE phpunit.xml src tests
git commit -m "feat: package skeleton with Pest + Testbench harness"
```

---

### Task 2: DTOs + Contracts

**Files:**
- Create: `src/Support/OAuthUser.php`, `src/Support/OAuthTokens.php`, `src/Support/CaptchaResponse.php`, `src/Contracts/OAuthProviderInterface.php`, `src/Contracts/CaptchaProviderInterface.php`
- Test: `tests/Unit/DtoTest.php`

**Interfaces:**
- Produces: `OAuthUser(string $id, ?string $email, ?string $name, ?string $avatarUrl, array $raw = [])`; `OAuthTokens(string $accessToken, ?string $refreshToken, ?int $expiresIn, ?string $idToken, string $tokenType = 'Bearer')`; `CaptchaResponse(bool $success, array $errorCodes = [], ?string $hostname = null, ?string $challengedAt = null, ?string $action = null, ?string $cdata = null)` with `failed(): bool`; the two contracts exactly as in the spec.

- [ ] **Step 1: Write failing DTO tests `tests/Unit/DtoTest.php`**

```php
<?php

use Appsbd\Auth\Support\CaptchaResponse;
use Appsbd\Auth\Support\OAuthTokens;
use Appsbd\Auth\Support\OAuthUser;

it('holds oauth user data', function () {
    $user = new OAuthUser(id: '123', email: 'a@b.c', name: 'A', avatarUrl: 'https://img', raw: ['sub' => '123']);
    expect($user->id)->toBe('123')
        ->and($user->email)->toBe('a@b.c')
        ->and($user->raw)->toBe(['sub' => '123']);
});

it('holds oauth tokens with defaults', function () {
    $tokens = new OAuthTokens(accessToken: 'at', refreshToken: null, expiresIn: 3599, idToken: null);
    expect($tokens->accessToken)->toBe('at')
        ->and($tokens->tokenType)->toBe('Bearer');
});

it('reports captcha failure state', function () {
    $ok = new CaptchaResponse(success: true);
    $bad = new CaptchaResponse(success: false, errorCodes: ['invalid-input-response']);
    expect($ok->failed())->toBeFalse()
        ->and($bad->failed())->toBeTrue()
        ->and($bad->errorCodes)->toBe(['invalid-input-response']);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/DtoTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the DTOs**

`src/Support/OAuthUser.php`:
```php
<?php

namespace Appsbd\Auth\Support;

final readonly class OAuthUser
{
    public function __construct(
        public string $id,
        public ?string $email,
        public ?string $name,
        public ?string $avatarUrl,
        public array $raw = [],
    ) {
    }
}
```

`src/Support/OAuthTokens.php`:
```php
<?php

namespace Appsbd\Auth\Support;

final readonly class OAuthTokens
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public ?int $expiresIn,
        public ?string $idToken,
        public string $tokenType = 'Bearer',
    ) {
    }
}
```

`src/Support/CaptchaResponse.php`:
```php
<?php

namespace Appsbd\Auth\Support;

final readonly class CaptchaResponse
{
    public function __construct(
        public bool $success,
        public array $errorCodes = [],
        public ?string $hostname = null,
        public ?string $challengedAt = null,
        public ?string $action = null,
        public ?string $cdata = null,
    ) {
    }

    public function failed(): bool
    {
        return ! $this->success;
    }
}
```

- [ ] **Step 4: Write the contracts**

`src/Contracts/OAuthProviderInterface.php`:
```php
<?php

namespace Appsbd\Auth\Contracts;

use Appsbd\Auth\Support\OAuthTokens;
use Appsbd\Auth\Support\OAuthUser;

interface OAuthProviderInterface
{
    public function generateAuthorizationUrl(?string $state = null, array $scopes = []): string;

    public function getTokensFromCode(string $code): OAuthTokens;

    public function getUserFromAccessToken(string $accessToken): OAuthUser;

    public function refreshToken(string $refreshToken): OAuthTokens;

    public function revokeToken(string $token): bool;
}
```

`src/Contracts/CaptchaProviderInterface.php`:
```php
<?php

namespace Appsbd\Auth\Contracts;

use Appsbd\Auth\Support\CaptchaResponse;

interface CaptchaProviderInterface
{
    public function verify(string $token, ?string $ip = null): CaptchaResponse;

    public function verifyOrFail(string $token, ?string $ip = null): CaptchaResponse;
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `composer test`
Expected: PASS (all tests).

- [ ] **Step 6: Commit**

```bash
git add src/Support src/Contracts tests/Unit/DtoTest.php
git commit -m "feat: readonly DTOs and provider contracts"
```

---

### Task 3: Exceptions

**Files:**
- Create: `src/Exceptions/AuthException.php`, `src/Exceptions/OAuthException.php`, `src/Exceptions/ConfigurationException.php`, `src/Exceptions/TurnstileException.php`
- Test: `tests/Unit/ExceptionsTest.php`

**Interfaces:**
- Consumes: `CaptchaResponse` from Task 2.
- Produces: `AuthException extends \Exception`; `OAuthException extends AuthException`; `ConfigurationException extends AuthException` with `static missing(string $key): self`; `TurnstileException extends AuthException` with `__construct(CaptchaResponse $response, string $message = 'Turnstile verification failed.')` exposing `public readonly CaptchaResponse $response`.

- [ ] **Step 1: Write failing tests `tests/Unit/ExceptionsTest.php`**

```php
<?php

use Appsbd\Auth\Exceptions\AuthException;
use Appsbd\Auth\Exceptions\ConfigurationException;
use Appsbd\Auth\Exceptions\OAuthException;
use Appsbd\Auth\Exceptions\TurnstileException;
use Appsbd\Auth\Support\CaptchaResponse;

it('has a common exception hierarchy', function () {
    expect(new OAuthException('x'))->toBeInstanceOf(AuthException::class)
        ->and(new ConfigurationException('x'))->toBeInstanceOf(AuthException::class);
});

it('names the missing config key', function () {
    $e = ConfigurationException::missing('appsbd-auth.google.client_id');
    expect($e->getMessage())->toContain('appsbd-auth.google.client_id');
});

it('carries the captcha response on turnstile failure', function () {
    $response = new CaptchaResponse(success: false, errorCodes: ['timeout-or-duplicate']);
    $e = new TurnstileException($response);
    expect($e)->toBeInstanceOf(AuthException::class)
        ->and($e->response->errorCodes)->toBe(['timeout-or-duplicate']);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/ExceptionsTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the exceptions**

`src/Exceptions/AuthException.php`:
```php
<?php

namespace Appsbd\Auth\Exceptions;

class AuthException extends \Exception
{
}
```

`src/Exceptions/OAuthException.php`:
```php
<?php

namespace Appsbd\Auth\Exceptions;

class OAuthException extends AuthException
{
}
```

`src/Exceptions/ConfigurationException.php`:
```php
<?php

namespace Appsbd\Auth\Exceptions;

class ConfigurationException extends AuthException
{
    public static function missing(string $key): self
    {
        return new self("Missing or empty required config value [{$key}]. Publish the config with `php artisan vendor:publish --tag=appsbd-auth-config` and set the corresponding environment variable.");
    }
}
```

`src/Exceptions/TurnstileException.php`:
```php
<?php

namespace Appsbd\Auth\Exceptions;

use Appsbd\Auth\Support\CaptchaResponse;

class TurnstileException extends AuthException
{
    public function __construct(
        public readonly CaptchaResponse $response,
        string $message = 'Turnstile verification failed.',
    ) {
        parent::__construct($message);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Exceptions tests/Unit/ExceptionsTest.php
git commit -m "feat: exception hierarchy"
```

---

### Task 4: Config file + provider config merge/publish

**Files:**
- Create: `config/appsbd-auth.php`
- Modify: `src/Providers/AuthServiceProvider.php`
- Test: `tests/Feature/ServiceProviderConfigTest.php`

**Interfaces:**
- Produces: merged config under key `appsbd-auth`; publish tag `appsbd-auth-config`.

- [ ] **Step 1: Write failing tests `tests/Feature/ServiceProviderConfigTest.php`**

```php
<?php

use Appsbd\Auth\Providers\AuthServiceProvider;
use Illuminate\Support\ServiceProvider;

it('merges the package config', function () {
    expect(config('appsbd-auth.turnstile.input_name'))->toBe('cf-turnstile-response')
        ->and(config('appsbd-auth.google.scopes'))->toBe(['openid', 'email', 'profile'])
        ->and((int) config('appsbd-auth.turnstile.timeout'))->toBe(10);
});

it('registers the config as publishable under the appsbd-auth-config tag', function () {
    $paths = ServiceProvider::pathsToPublish(AuthServiceProvider::class, 'appsbd-auth-config');
    expect($paths)->not->toBeEmpty();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/ServiceProviderConfigTest.php`
Expected: FAIL — config values are null / publish paths empty.

- [ ] **Step 3: Write `config/appsbd-auth.php`**

```php
<?php

return [

    'google' => [
        'client_id'     => env('APPSBD_GOOGLE_CLIENT_ID'),
        'client_secret' => env('APPSBD_GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('APPSBD_GOOGLE_REDIRECT_URI'),
        'scopes'        => ['openid', 'email', 'profile'],
    ],

    'turnstile' => [
        'site_key'   => env('APPSBD_TURNSTILE_SITE_KEY'),
        'secret'     => env('APPSBD_TURNSTILE_SECRET'),
        'timeout'    => env('APPSBD_TURNSTILE_TIMEOUT', 10),
        'input_name' => 'cf-turnstile-response',
    ],

];
```

- [ ] **Step 4: Update the provider**

Replace `register()` and `boot()` in `src/Providers/AuthServiceProvider.php`:
```php
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/appsbd-auth.php', 'appsbd-auth');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/appsbd-auth.php' => config_path('appsbd-auth.php'),
        ], 'appsbd-auth-config');
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `composer test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add config src/Providers/AuthServiceProvider.php tests/Feature/ServiceProviderConfigTest.php
git commit -m "feat: package config with merge and publish support"
```

---

### Task 5: GoogleOAuthService — authorization URL + state handling

**Files:**
- Create: `src/Services/GoogleOAuthService.php`
- Test: `tests/Feature/GoogleAuthorizationUrlTest.php`

**Interfaces:**
- Consumes: `OAuthProviderInterface`, DTOs, `ConfigurationException`.
- Produces: `GoogleOAuthService implements OAuthProviderInterface`, `__construct(array $config)`. Session key constant `appsbd-auth.google.state` (public const `STATE_SESSION_KEY`). Later tasks add the HTTP methods; this task stubs them to `throw new \BadMethodCallException('Not implemented yet.')`.

- [ ] **Step 1: Write failing tests `tests/Feature/GoogleAuthorizationUrlTest.php`**

```php
<?php

use Appsbd\Auth\Exceptions\ConfigurationException;
use Appsbd\Auth\Services\GoogleOAuthService;

function googleService(array $overrides = []): GoogleOAuthService
{
    return new GoogleOAuthService(array_merge([
        'client_id'     => 'cid',
        'client_secret' => 'secret',
        'redirect'      => 'https://app.test/auth/google/callback',
        'scopes'        => ['openid', 'email', 'profile'],
    ], $overrides));
}

it('generates an authorization url with default scopes and stores random state in the session', function () {
    $url = googleService()->generateAuthorizationUrl();

    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?')
        ->and($query['client_id'])->toBe('cid')
        ->and($query['redirect_uri'])->toBe('https://app.test/auth/google/callback')
        ->and($query['response_type'])->toBe('code')
        ->and($query['scope'])->toBe('openid email profile')
        ->and(strlen($query['state']))->toBe(40)
        ->and(session()->get(GoogleOAuthService::STATE_SESSION_KEY))->toBe($query['state']);
});

it('uses explicit state without touching the session', function () {
    $url = googleService()->generateAuthorizationUrl(state: 'my-custom-state');

    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($query['state'])->toBe('my-custom-state')
        ->and(session()->has(GoogleOAuthService::STATE_SESSION_KEY))->toBeFalse();
});

it('lets explicit scopes override config scopes', function () {
    $url = googleService()->generateAuthorizationUrl(scopes: ['email']);

    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('email');
});

it('throws a ConfigurationException naming the key when client_id is missing', function () {
    googleService(['client_id' => null])->generateAuthorizationUrl();
})->throws(ConfigurationException::class, 'appsbd-auth.google.client_id');

it('throws a ConfigurationException naming the key when redirect is missing', function () {
    googleService(['redirect' => ''])->generateAuthorizationUrl();
})->throws(ConfigurationException::class, 'appsbd-auth.google.redirect');
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/GoogleAuthorizationUrlTest.php`
Expected: FAIL — `GoogleOAuthService` not found.

- [ ] **Step 3: Write `src/Services/GoogleOAuthService.php`**

```php
<?php

namespace Appsbd\Auth\Services;

use Appsbd\Auth\Contracts\OAuthProviderInterface;
use Appsbd\Auth\Exceptions\ConfigurationException;
use Appsbd\Auth\Support\OAuthTokens;
use Appsbd\Auth\Support\OAuthUser;
use Illuminate\Support\Str;

class GoogleOAuthService implements OAuthProviderInterface
{
    public const STATE_SESSION_KEY = 'appsbd-auth.google.state';

    protected const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    protected const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
    protected const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public function __construct(protected array $config)
    {
    }

    public function generateAuthorizationUrl(?string $state = null, array $scopes = []): string
    {
        $clientId = $this->requireConfig('client_id');
        $redirect = $this->requireConfig('redirect');

        if ($state === null) {
            $state = Str::random(40);
            session()->put(self::STATE_SESSION_KEY, $state);
        }

        $query = http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirect,
            'response_type' => 'code',
            'scope'         => implode(' ', $scopes ?: ($this->config['scopes'] ?? ['openid', 'email', 'profile'])),
            'state'         => $state,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);

        return self::AUTHORIZE_URL.'?'.$query;
    }

    public function getTokensFromCode(string $code): OAuthTokens
    {
        throw new \BadMethodCallException('Not implemented yet.');
    }

    public function getUserFromAccessToken(string $accessToken): OAuthUser
    {
        throw new \BadMethodCallException('Not implemented yet.');
    }

    public function refreshToken(string $refreshToken): OAuthTokens
    {
        throw new \BadMethodCallException('Not implemented yet.');
    }

    public function revokeToken(string $token): bool
    {
        throw new \BadMethodCallException('Not implemented yet.');
    }

    protected function requireConfig(string $key): string
    {
        $value = $this->config[$key] ?? null;

        if ($value === null || $value === '') {
            throw ConfigurationException::missing("appsbd-auth.google.{$key}");
        }

        return (string) $value;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Services/GoogleOAuthService.php tests/Feature/GoogleAuthorizationUrlTest.php
git commit -m "feat: google authorization url generation with session state"
```

---

### Task 6: GoogleOAuthService — token exchange, userinfo, refresh, revoke

**Files:**
- Modify: `src/Services/GoogleOAuthService.php` (replace the four stubs, add helpers)
- Test: `tests/Feature/GoogleTokenTest.php`

**Interfaces:**
- Consumes: `googleService()` helper already defined in `tests/Feature/GoogleAuthorizationUrlTest.php` (Pest loads all test files; reuse it — do NOT redefine).
- Produces: working `getTokensFromCode`, `getUserFromAccessToken`, `refreshToken`, `revokeToken`; protected `mapTokens(array $data, ?string $fallbackRefreshToken = null): OAuthTokens`; protected `googleRequest(\Closure $call, string $context): \Illuminate\Http\Client\Response`.

- [ ] **Step 1: Write failing tests `tests/Feature/GoogleTokenTest.php`**

```php
<?php

use Appsbd\Auth\Exceptions\OAuthException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('exchanges an authorization code for tokens', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token'  => 'at-123',
            'refresh_token' => 'rt-456',
            'expires_in'    => 3599,
            'id_token'      => 'idt-789',
            'token_type'    => 'Bearer',
        ]),
    ]);

    $tokens = googleService()->getTokensFromCode('the-code');

    expect($tokens->accessToken)->toBe('at-123')
        ->and($tokens->refreshToken)->toBe('rt-456')
        ->and($tokens->expiresIn)->toBe(3599)
        ->and($tokens->idToken)->toBe('idt-789');

    Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'the-code'
        && $request['client_id'] === 'cid'
        && $request['client_secret'] === 'secret');
});

it('maps google token errors to OAuthException with the google message', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'error'             => 'invalid_grant',
            'error_description' => 'Malformed auth code.',
        ], 400),
    ]);

    googleService()->getTokensFromCode('bad-code');
})->throws(OAuthException::class, 'Malformed auth code.');

it('maps connection failures to OAuthException', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28'));

    googleService()->getTokensFromCode('any');
})->throws(OAuthException::class);

it('fetches the user profile with a bearer token', function () {
    Http::fake([
        'openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub'     => 'g-1',
            'email'   => 'user@example.com',
            'name'    => 'Test User',
            'picture' => 'https://img.example/p.png',
        ]),
    ]);

    $user = googleService()->getUserFromAccessToken('at-123');

    expect($user->id)->toBe('g-1')
        ->and($user->email)->toBe('user@example.com')
        ->and($user->name)->toBe('Test User')
        ->and($user->avatarUrl)->toBe('https://img.example/p.png')
        ->and($user->raw['sub'])->toBe('g-1');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer at-123'));
});

it('refreshes tokens and keeps the old refresh token when google omits it', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'new-at',
            'expires_in'   => 3599,
            'token_type'   => 'Bearer',
        ]),
    ]);

    $tokens = googleService()->refreshToken('rt-456');

    expect($tokens->accessToken)->toBe('new-at')
        ->and($tokens->refreshToken)->toBe('rt-456');

    Http::assertSent(fn ($request) => $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'rt-456');
});

it('revokes a token and reports the result', function () {
    Http::fake(['oauth2.googleapis.com/revoke' => Http::response([], 200)]);
    expect(googleService()->revokeToken('at-123'))->toBeTrue();

    Http::fake(['oauth2.googleapis.com/revoke' => Http::response([], 400)]);
    expect(googleService()->revokeToken('expired'))->toBeFalse();
});

it('throws OAuthException when the token response has no access_token', function () {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['token_type' => 'Bearer'])]);

    googleService()->getTokensFromCode('code');
})->throws(OAuthException::class);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/GoogleTokenTest.php`
Expected: FAIL — `BadMethodCallException: Not implemented yet.`

- [ ] **Step 3: Replace the stubs in `src/Services/GoogleOAuthService.php`**

Add imports at the top of the file:
```php
use Appsbd\Auth\Exceptions\OAuthException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
```

Replace the four stub methods and add helpers:
```php
    public function getTokensFromCode(string $code): OAuthTokens
    {
        $response = $this->googleRequest(fn () => Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => $this->requireConfig('client_id'),
            'client_secret' => $this->requireConfig('client_secret'),
            'redirect_uri'  => $this->requireConfig('redirect'),
            'grant_type'    => 'authorization_code',
            'code'          => $code,
        ]), 'exchange authorization code');

        return $this->mapTokens($response->json() ?? []);
    }

    public function getUserFromAccessToken(string $accessToken): OAuthUser
    {
        $response = $this->googleRequest(
            fn () => Http::withToken($accessToken)->get(self::USERINFO_URL),
            'fetch user profile'
        );

        $data = $response->json() ?? [];

        if (! isset($data['sub'])) {
            throw new OAuthException('Google userinfo response did not include a subject identifier.');
        }

        return new OAuthUser(
            id: (string) $data['sub'],
            email: $data['email'] ?? null,
            name: $data['name'] ?? null,
            avatarUrl: $data['picture'] ?? null,
            raw: $data,
        );
    }

    public function refreshToken(string $refreshToken): OAuthTokens
    {
        $response = $this->googleRequest(fn () => Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => $this->requireConfig('client_id'),
            'client_secret' => $this->requireConfig('client_secret'),
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]), 'refresh access token');

        return $this->mapTokens($response->json() ?? [], $refreshToken);
    }

    public function revokeToken(string $token): bool
    {
        try {
            return Http::asForm()->post(self::REVOKE_URL, ['token' => $token])->successful();
        } catch (\Throwable $e) {
            throw new OAuthException("Could not reach Google to revoke token: {$e->getMessage()}", previous: $e);
        }
    }

    protected function googleRequest(\Closure $call, string $context): Response
    {
        try {
            $response = $call();
        } catch (\Throwable $e) {
            throw new OAuthException("Could not reach Google to {$context}: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            $message = $response->json('error_description')
                ?? $response->json('error')
                ?? "HTTP {$response->status()}";

            throw new OAuthException("Google refused to {$context}: {$message}");
        }

        return $response;
    }

    protected function mapTokens(array $data, ?string $fallbackRefreshToken = null): OAuthTokens
    {
        if (empty($data['access_token'])) {
            throw new OAuthException('Google token response did not include an access_token.');
        }

        return new OAuthTokens(
            accessToken: (string) $data['access_token'],
            refreshToken: $data['refresh_token'] ?? $fallbackRefreshToken,
            expiresIn: isset($data['expires_in']) ? (int) $data['expires_in'] : null,
            idToken: $data['id_token'] ?? null,
            tokenType: $data['token_type'] ?? 'Bearer',
        );
    }
```

Note: `previous:` named argument works because `OAuthException` inherits `\Exception::__construct(string $message = "", int $code = 0, ?Throwable $previous = null)` — pass it as `new OAuthException($msg, previous: $e)`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Services/GoogleOAuthService.php tests/Feature/GoogleTokenTest.php
git commit -m "feat: google token exchange, userinfo, refresh, revoke with error mapping"
```

---

### Task 7: GoogleOAuthService — redirect(), callback(), Google events

**Files:**
- Create: `src/Events/GoogleLoginSucceeded.php`, `src/Events/GoogleLoginFailed.php`
- Modify: `src/Services/GoogleOAuthService.php` (add `redirect()` and `callback()`)
- Test: `tests/Feature/GoogleCallbackTest.php`

**Interfaces:**
- Produces: `GoogleLoginSucceeded(OAuthUser $user, OAuthTokens $tokens)` (public readonly props); `GoogleLoginFailed(string $reason, ?\Throwable $exception = null)`; `redirect(?string $state = null, array $scopes = []): \Illuminate\Http\RedirectResponse`; `callback(?string $code = null, ?string $state = null): array{user: OAuthUser, tokens: OAuthTokens}`.
- State rules in `callback()`: `$state` (param or request query fallback) is the value returned by Google. If a session state exists it must `hash_equals` the returned state (session state is pulled/forgotten either way); if no session state exists, an explicitly passed `$state` param bypasses validation (stateless mode); if neither exists → `OAuthException`.

- [ ] **Step 1: Write failing tests `tests/Feature/GoogleCallbackTest.php`**

```php
<?php

use Appsbd\Auth\Events\GoogleLoginFailed;
use Appsbd\Auth\Events\GoogleLoginSucceeded;
use Appsbd\Auth\Exceptions\OAuthException;
use Appsbd\Auth\Services\GoogleOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function fakeGoogleHttp(): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'at-123', 'refresh_token' => 'rt-456',
            'expires_in' => 3599, 'id_token' => 'idt', 'token_type' => 'Bearer',
        ]),
        'openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'g-1', 'email' => 'user@example.com', 'name' => 'Test User', 'picture' => null,
        ]),
    ]);
}

it('redirect() returns a RedirectResponse to the authorization url', function () {
    $response = googleService()->redirect();

    expect($response)->toBeInstanceOf(Illuminate\Http\RedirectResponse::class)
        ->and($response->getTargetUrl())->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?');
});

it('callback() validates session state, returns user + tokens, fires GoogleLoginSucceeded', function () {
    fakeGoogleHttp();
    Event::fake();
    session()->put(GoogleOAuthService::STATE_SESSION_KEY, 'expected-state');

    $result = googleService()->callback(code: 'the-code', state: 'expected-state');

    expect($result['user']->email)->toBe('user@example.com')
        ->and($result['tokens']->accessToken)->toBe('at-123')
        ->and(session()->has(GoogleOAuthService::STATE_SESSION_KEY))->toBeFalse();

    Event::assertDispatched(GoogleLoginSucceeded::class, fn ($e) => $e->user->id === 'g-1' && $e->tokens->accessToken === 'at-123');
});

it('callback() reads code and state from the current request when not passed', function () {
    fakeGoogleHttp();
    session()->put(GoogleOAuthService::STATE_SESSION_KEY, 'req-state');
    $this->instance('request', Request::create('/auth/google/callback', 'GET', [
        'code' => 'the-code', 'state' => 'req-state',
    ]));

    $result = googleService()->callback();

    expect($result['user']->id)->toBe('g-1');
});

it('callback() throws and fires GoogleLoginFailed on state mismatch', function () {
    Event::fake();
    session()->put(GoogleOAuthService::STATE_SESSION_KEY, 'expected-state');

    expect(fn () => googleService()->callback(code: 'x', state: 'tampered'))
        ->toThrow(OAuthException::class);

    Event::assertDispatched(GoogleLoginFailed::class);
});

it('callback() bypasses session validation when explicit state is supplied with no session state', function () {
    fakeGoogleHttp();

    $result = googleService()->callback(code: 'the-code', state: 'client-managed-state');

    expect($result['user']->id)->toBe('g-1');
});

it('callback() throws when no state is available at all', function () {
    $this->instance('request', Request::create('/auth/google/callback', 'GET', ['code' => 'x']));

    googleService()->callback();
})->throws(OAuthException::class);

it('callback() fires GoogleLoginFailed when the token exchange fails', function () {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);
    Event::fake();

    expect(fn () => googleService()->callback(code: 'bad', state: 'client-state'))
        ->toThrow(OAuthException::class);

    Event::assertDispatched(GoogleLoginFailed::class, fn ($e) => $e->exception instanceof OAuthException);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/GoogleCallbackTest.php`
Expected: FAIL — events/methods missing.

- [ ] **Step 3: Write the events**

`src/Events/GoogleLoginSucceeded.php`:
```php
<?php

namespace Appsbd\Auth\Events;

use Appsbd\Auth\Support\OAuthTokens;
use Appsbd\Auth\Support\OAuthUser;

final class GoogleLoginSucceeded
{
    public function __construct(
        public readonly OAuthUser $user,
        public readonly OAuthTokens $tokens,
    ) {
    }
}
```

`src/Events/GoogleLoginFailed.php`:
```php
<?php

namespace Appsbd\Auth\Events;

final class GoogleLoginFailed
{
    public function __construct(
        public readonly string $reason,
        public readonly ?\Throwable $exception = null,
    ) {
    }
}
```

- [ ] **Step 4: Add `redirect()` and `callback()` to `GoogleOAuthService`**

Add imports:
```php
use Appsbd\Auth\Events\GoogleLoginFailed;
use Appsbd\Auth\Events\GoogleLoginSucceeded;
use Illuminate\Http\RedirectResponse;
```

Add methods:
```php
    public function redirect(?string $state = null, array $scopes = []): RedirectResponse
    {
        return new RedirectResponse($this->generateAuthorizationUrl($state, $scopes));
    }

    /**
     * @return array{user: OAuthUser, tokens: OAuthTokens}
     */
    public function callback(?string $code = null, ?string $state = null): array
    {
        try {
            $request = request();
            $code ??= $request->query('code');
            $returnedState = $state ?? $request->query('state');
            $sessionState = session()->pull(self::STATE_SESSION_KEY);

            if ($sessionState !== null) {
                if (! is_string($returnedState) || ! hash_equals($sessionState, $returnedState)) {
                    throw new OAuthException('OAuth state mismatch: the state returned by Google does not match the one stored in the session.');
                }
            } elseif ($state === null) {
                throw new OAuthException('No OAuth state available: nothing stored in the session and none passed to callback().');
            }

            if (! is_string($code) || $code === '') {
                throw new OAuthException('No authorization code was provided to callback().');
            }

            $tokens = $this->getTokensFromCode($code);
            $user = $this->getUserFromAccessToken($tokens->accessToken);
        } catch (\Throwable $e) {
            event(new GoogleLoginFailed($e->getMessage(), $e));

            throw $e;
        }

        event(new GoogleLoginSucceeded($user, $tokens));

        return ['user' => $user, 'tokens' => $tokens];
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `composer test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Events src/Services/GoogleOAuthService.php tests/Feature/GoogleCallbackTest.php
git commit -m "feat: google redirect/callback conveniences with login events"
```

---

### Task 8: TurnstileService + Turnstile events

**Files:**
- Create: `src/Services/TurnstileService.php`, `src/Events/TurnstileVerified.php`, `src/Events/TurnstileFailed.php`
- Test: `tests/Feature/TurnstileServiceTest.php`

**Interfaces:**
- Produces: `TurnstileService implements CaptchaProviderInterface`, `__construct(array $config)`; `TurnstileVerified(CaptchaResponse $response)`; `TurnstileFailed(CaptchaResponse $response)` (public readonly props).

- [ ] **Step 1: Write failing tests `tests/Feature/TurnstileServiceTest.php`**

```php
<?php

use Appsbd\Auth\Events\TurnstileFailed;
use Appsbd\Auth\Events\TurnstileVerified;
use Appsbd\Auth\Exceptions\ConfigurationException;
use Appsbd\Auth\Exceptions\TurnstileException;
use Appsbd\Auth\Services\TurnstileService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function turnstileService(array $overrides = []): TurnstileService
{
    return new TurnstileService(array_merge([
        'site_key' => 'sk',
        'secret'   => 'ts-secret',
        'timeout'  => 10,
    ], $overrides));
}

it('verifies a token successfully and fires TurnstileVerified', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => true, 'hostname' => 'app.test',
            'challenge_ts' => '2026-07-22T00:00:00Z', 'action' => 'login', 'cdata' => 'x',
        ]),
    ]);
    Event::fake();

    $result = turnstileService()->verify('the-token', '1.2.3.4');

    expect($result->success)->toBeTrue()
        ->and($result->hostname)->toBe('app.test')
        ->and($result->action)->toBe('login');

    Http::assertSent(fn ($request) => $request['secret'] === 'ts-secret'
        && $request['response'] === 'the-token'
        && $request['remoteip'] === '1.2.3.4');

    Event::assertDispatched(TurnstileVerified::class);
});

it('returns failure codes and fires TurnstileFailed', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => false, 'error-codes' => ['invalid-input-response'],
        ]),
    ]);
    Event::fake();

    $result = turnstileService()->verify('bad-token');

    expect($result->failed())->toBeTrue()
        ->and($result->errorCodes)->toBe(['invalid-input-response']);

    Event::assertDispatched(TurnstileFailed::class);
});

it('never throws on network failure — returns internal-error', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $result = turnstileService()->verify('any');

    expect($result->failed())->toBeTrue()
        ->and($result->errorCodes)->toBe(['internal-error']);
});

it('treats HTTP 5xx as internal-error without throwing', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response('oops', 502)]);

    $result = turnstileService()->verify('any');

    expect($result->failed())->toBeTrue()
        ->and($result->errorCodes)->toBe(['internal-error']);
});

it('verifyOrFail throws TurnstileException carrying the response', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => false, 'error-codes' => ['timeout-or-duplicate'],
        ]),
    ]);

    try {
        turnstileService()->verifyOrFail('stale');
        $this->fail('Expected TurnstileException');
    } catch (TurnstileException $e) {
        expect($e->response->errorCodes)->toBe(['timeout-or-duplicate']);
    }
});

it('verifyOrFail returns the response on success', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    expect(turnstileService()->verifyOrFail('good')->success)->toBeTrue();
});

it('throws ConfigurationException when secret is missing', function () {
    turnstileService(['secret' => null])->verify('token');
})->throws(ConfigurationException::class, 'appsbd-auth.turnstile.secret');
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/TurnstileServiceTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write the events**

`src/Events/TurnstileVerified.php`:
```php
<?php

namespace Appsbd\Auth\Events;

use Appsbd\Auth\Support\CaptchaResponse;

final class TurnstileVerified
{
    public function __construct(public readonly CaptchaResponse $response)
    {
    }
}
```

`src/Events/TurnstileFailed.php`:
```php
<?php

namespace Appsbd\Auth\Events;

use Appsbd\Auth\Support\CaptchaResponse;

final class TurnstileFailed
{
    public function __construct(public readonly CaptchaResponse $response)
    {
    }
}
```

- [ ] **Step 4: Write `src/Services/TurnstileService.php`**

```php
<?php

namespace Appsbd\Auth\Services;

use Appsbd\Auth\Contracts\CaptchaProviderInterface;
use Appsbd\Auth\Events\TurnstileFailed;
use Appsbd\Auth\Events\TurnstileVerified;
use Appsbd\Auth\Exceptions\ConfigurationException;
use Appsbd\Auth\Exceptions\TurnstileException;
use Appsbd\Auth\Support\CaptchaResponse;
use Illuminate\Support\Facades\Http;

class TurnstileService implements CaptchaProviderInterface
{
    protected const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(protected array $config)
    {
    }

    public function verify(string $token, ?string $ip = null): CaptchaResponse
    {
        $secret = $this->config['secret'] ?? null;

        if ($secret === null || $secret === '') {
            throw ConfigurationException::missing('appsbd-auth.turnstile.secret');
        }

        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 10))
                ->asForm()
                ->post(self::VERIFY_URL, [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);

            if ($response->failed()) {
                $result = new CaptchaResponse(success: false, errorCodes: ['internal-error']);
            } else {
                $data = $response->json() ?? [];
                $result = new CaptchaResponse(
                    success: (bool) ($data['success'] ?? false),
                    errorCodes: $data['error-codes'] ?? [],
                    hostname: $data['hostname'] ?? null,
                    challengedAt: $data['challenge_ts'] ?? null,
                    action: $data['action'] ?? null,
                    cdata: $data['cdata'] ?? null,
                );
            }
        } catch (\Throwable) {
            $result = new CaptchaResponse(success: false, errorCodes: ['internal-error']);
        }

        event($result->success ? new TurnstileVerified($result) : new TurnstileFailed($result));

        return $result;
    }

    public function verifyOrFail(string $token, ?string $ip = null): CaptchaResponse
    {
        $result = $this->verify($token, $ip);

        if ($result->failed()) {
            throw new TurnstileException($result);
        }

        return $result;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `composer test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Services/TurnstileService.php src/Events tests/Feature/TurnstileServiceTest.php
git commit -m "feat: turnstile verification service with events"
```

---

### Task 9: Container bindings + facades

**Files:**
- Create: `src/Facades/GoogleOAuth.php`, `src/Facades/Turnstile.php`
- Modify: `src/Providers/AuthServiceProvider.php` (`register()`), `tests/TestCase.php` (add `getPackageAliases`)
- Test: `tests/Feature/ContainerTest.php`

**Interfaces:**
- Produces: container singletons `GoogleOAuthService` / `TurnstileService`, aliased to `OAuthProviderInterface` / `CaptchaProviderInterface`; facades `Appsbd\Auth\Facades\GoogleOAuth` and `Appsbd\Auth\Facades\Turnstile` accessing those services; testbench aliases `GoogleOAuth` / `Turnstile`.

- [ ] **Step 1: Write failing tests `tests/Feature/ContainerTest.php`**

```php
<?php

use Appsbd\Auth\Contracts\CaptchaProviderInterface;
use Appsbd\Auth\Contracts\OAuthProviderInterface;
use Appsbd\Auth\Facades\GoogleOAuth;
use Appsbd\Auth\Services\GoogleOAuthService;
use Appsbd\Auth\Services\TurnstileService;

it('binds interfaces to singleton implementations', function () {
    expect(app(OAuthProviderInterface::class))->toBeInstanceOf(GoogleOAuthService::class)
        ->and(app(CaptchaProviderInterface::class))->toBeInstanceOf(TurnstileService::class)
        ->and(app(GoogleOAuthService::class))->toBe(app(OAuthProviderInterface::class))
        ->and(app(TurnstileService::class))->toBe(app(CaptchaProviderInterface::class));
});

it('resolves services through facades using app config', function () {
    config()->set('appsbd-auth.google.client_id', 'cid');
    config()->set('appsbd-auth.google.redirect', 'https://app.test/cb');

    $url = GoogleOAuth::generateAuthorizationUrl(state: 's');

    expect($url)->toContain('client_id=cid');
});

it('registers the facade aliases', function () {
    expect(class_exists('GoogleOAuth'))->toBeTrue()
        ->and(class_exists('Turnstile'))->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/ContainerTest.php`
Expected: FAIL — binding resolution / facades missing.

- [ ] **Step 3: Write the facades**

`src/Facades/GoogleOAuth.php`:
```php
<?php

namespace Appsbd\Auth\Facades;

use Appsbd\Auth\Services\GoogleOAuthService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string generateAuthorizationUrl(?string $state = null, array $scopes = [])
 * @method static \Illuminate\Http\RedirectResponse redirect(?string $state = null, array $scopes = [])
 * @method static array{user: \Appsbd\Auth\Support\OAuthUser, tokens: \Appsbd\Auth\Support\OAuthTokens} callback(?string $code = null, ?string $state = null)
 * @method static \Appsbd\Auth\Support\OAuthTokens getTokensFromCode(string $code)
 * @method static \Appsbd\Auth\Support\OAuthUser getUserFromAccessToken(string $accessToken)
 * @method static \Appsbd\Auth\Support\OAuthTokens refreshToken(string $refreshToken)
 * @method static bool revokeToken(string $token)
 *
 * @see GoogleOAuthService
 */
class GoogleOAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GoogleOAuthService::class;
    }
}
```

`src/Facades/Turnstile.php`:
```php
<?php

namespace Appsbd\Auth\Facades;

use Appsbd\Auth\Services\TurnstileService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Appsbd\Auth\Support\CaptchaResponse verify(string $token, ?string $ip = null)
 * @method static \Appsbd\Auth\Support\CaptchaResponse verifyOrFail(string $token, ?string $ip = null)
 *
 * @see TurnstileService
 */
class Turnstile extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TurnstileService::class;
    }
}
```

- [ ] **Step 4: Register bindings in the provider and aliases in TestCase**

In `AuthServiceProvider::register()`, after `mergeConfigFrom`, add (with imports for the two services and two contracts):
```php
        $this->app->singleton(GoogleOAuthService::class, function ($app) {
            return new GoogleOAuthService($app['config']->get('appsbd-auth.google', []));
        });
        $this->app->alias(GoogleOAuthService::class, OAuthProviderInterface::class);

        $this->app->singleton(TurnstileService::class, function ($app) {
            return new TurnstileService($app['config']->get('appsbd-auth.turnstile', []));
        });
        $this->app->alias(TurnstileService::class, CaptchaProviderInterface::class);
```

In `tests/TestCase.php` add:
```php
    protected function getPackageAliases($app): array
    {
        return [
            'GoogleOAuth' => \Appsbd\Auth\Facades\GoogleOAuth::class,
            'Turnstile'   => \Appsbd\Auth\Facades\Turnstile::class,
        ];
    }
```

Note: the facade test sets config *before* first resolution, so the singleton picks up the values — keep that ordering in any new tests.

- [ ] **Step 5: Run tests to verify they pass**

Run: `composer test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Facades src/Providers/AuthServiceProvider.php tests/TestCase.php tests/Feature/ContainerTest.php
git commit -m "feat: container singletons and facades"
```

---

### Task 10: VerifyTurnstile middleware + alias

**Files:**
- Create: `src/Http/Middleware/VerifyTurnstile.php`
- Modify: `src/Providers/AuthServiceProvider.php` (`boot()`: alias middleware)
- Test: `tests/Feature/MiddlewareTest.php`

**Interfaces:**
- Consumes: `CaptchaProviderInterface` (constructor-injected).
- Produces: middleware alias `turnstile`; on failure JSON 422 `{message, errors: {<input_name>: [...]}}` when `expectsJson()`, else redirect back with validation error under the input name key.

- [ ] **Step 1: Write failing tests `tests/Feature/MiddlewareTest.php`**

```php
<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('appsbd-auth.turnstile.secret', 'ts-secret');

    Route::post('/protected', fn () => response()->json(['ok' => true]))
        ->middleware(['web', 'turnstile']);
});

it('passes the request through on successful verification', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $this->post('/protected', ['cf-turnstile-response' => 'good'])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('returns JSON 422 with an error payload for json requests', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    $this->postJson('/protected', ['cf-turnstile-response' => 'bad'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cf-turnstile-response']);
});

it('redirects back with a validation error for web requests', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $this->from('/form')
        ->post('/protected', ['cf-turnstile-response' => 'bad'])
        ->assertRedirect('/form')
        ->assertSessionHasErrors(['cf-turnstile-response']);
});

it('reads the token from a configurable input name', function () {
    config()->set('appsbd-auth.turnstile.input_name', 'captcha_token');
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $this->post('/protected', ['captcha_token' => 'good'])->assertOk();

    Http::assertSent(fn ($request) => $request['response'] === 'good');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/MiddlewareTest.php`
Expected: FAIL — middleware alias `turnstile` not defined.

- [ ] **Step 3: Write `src/Http/Middleware/VerifyTurnstile.php`**

```php
<?php

namespace Appsbd\Auth\Http\Middleware;

use Appsbd\Auth\Contracts\CaptchaProviderInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
    public function __construct(protected CaptchaProviderInterface $captcha)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $inputName = config('appsbd-auth.turnstile.input_name', 'cf-turnstile-response');
        $message = 'Captcha verification failed. Please try again.';

        $result = $this->captcha->verify((string) $request->input($inputName, ''), $request->ip());

        if ($result->success) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'errors'  => [$inputName => [$message]],
            ], 422);
        }

        return back()
            ->withInput($request->except($inputName))
            ->withErrors([$inputName => $message]);
    }
}
```

- [ ] **Step 4: Register the alias in `AuthServiceProvider::boot()`**

Add (import `VerifyTurnstile`):
```php
        $this->app['router']->aliasMiddleware('turnstile', VerifyTurnstile::class);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `composer test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Http src/Providers/AuthServiceProvider.php tests/Feature/MiddlewareTest.php
git commit -m "feat: turnstile middleware with json and redirect failure modes"
```

---

### Task 11: Validation rule (object + string)

**Files:**
- Create: `src/Validation/TurnstileRule.php`
- Modify: `src/Providers/AuthServiceProvider.php` (`boot()`: register string rule)
- Test: `tests/Feature/ValidationRuleTest.php`

**Interfaces:**
- Produces: `TurnstileRule implements Illuminate\Contracts\Validation\ValidationRule`; string rule `'turnstile'` registered via `Validator::extend`. Both resolve `CaptchaProviderInterface` from the container and verify with `request()->ip()`.

- [ ] **Step 1: Write failing tests `tests/Feature/ValidationRuleTest.php`**

```php
<?php

use Appsbd\Auth\Validation\TurnstileRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

beforeEach(fn () => config()->set('appsbd-auth.turnstile.secret', 'ts-secret'));

it('passes as a rule object when verification succeeds', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $v = Validator::make(['cf-turnstile-response' => 'good'], ['cf-turnstile-response' => [new TurnstileRule]]);

    expect($v->passes())->toBeTrue();
});

it('fails as a rule object when verification fails', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $v = Validator::make(['cf-turnstile-response' => 'bad'], ['cf-turnstile-response' => [new TurnstileRule]]);

    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('cf-turnstile-response'))->toBeTrue();
});

it('works as the string rule "turnstile"', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    expect(Validator::make(['t' => 'good'], ['t' => 'turnstile'])->passes())->toBeTrue();

    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
    expect(Validator::make(['t' => 'bad'], ['t' => 'turnstile'])->fails())->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/ValidationRuleTest.php`
Expected: FAIL — rule class/string rule missing.

- [ ] **Step 3: Write `src/Validation/TurnstileRule.php`**

```php
<?php

namespace Appsbd\Auth\Validation;

use Appsbd\Auth\Contracts\CaptchaProviderInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TurnstileRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = app(CaptchaProviderInterface::class)->verify((string) $value, request()->ip());

        if ($result->failed()) {
            $fail('The :attribute field failed captcha verification.');
        }
    }
}
```

- [ ] **Step 4: Register the string rule in `AuthServiceProvider::boot()`**

Add (import `Illuminate\Support\Facades\Validator` and `CaptchaProviderInterface`):
```php
        Validator::extend('turnstile', function ($attribute, $value) {
            return app(CaptchaProviderInterface::class)
                ->verify((string) $value, request()->ip())
                ->success;
        }, 'The :attribute field failed captcha verification.');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `composer test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Validation src/Providers/AuthServiceProvider.php tests/Feature/ValidationRuleTest.php
git commit -m "feat: turnstile validation rule (object and string forms)"
```

---

### Task 12: CI workflow

**Files:**
- Create: `.github/workflows/tests.yml`

- [ ] **Step 1: Write the workflow**

```yaml
name: tests

on:
  push:
  pull_request:

jobs:
  tests:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['8.3', '8.4']
        laravel: ['12.*', '13.*']
        include:
          - laravel: '12.*'
            testbench: '10.*'
          - laravel: '13.*'
            testbench: '11.*'

    name: PHP ${{ matrix.php }} - Laravel ${{ matrix.laravel }}

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none

      - name: Install dependencies
        run: |
          composer require "illuminate/support:${{ matrix.laravel }}" --no-interaction --no-update
          composer require --dev "orchestra/testbench:${{ matrix.testbench }}" --no-interaction --no-update
          composer update --prefer-dist --no-interaction --no-progress

      - name: Run tests
        run: composer test
```

- [ ] **Step 2: Validate locally**

Run: `composer test` (full suite still green) and confirm the YAML parses: `php -r "echo 'ok';"` isn't enough — just visually confirm indentation or run any YAML linter if available.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/tests.yml
git commit -m "ci: test matrix php 8.3/8.4 x laravel 12/13"
```

---

### Task 13: Root docs (README, CHANGELOG, CONTRIBUTING, SECURITY)

**Files:**
- Create: `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `SECURITY.md`

- [ ] **Step 1: Write `README.md`**

Sections (write complete prose for each):
1. Title + one-line description + badges placeholder-free (just title/description; no fake badge URLs).
2. **Features** — Google OAuth2 (hand-rolled, no Socialite), Cloudflare Turnstile, events, middleware, validation rule, facades, DI-first.
3. **Requirements** — PHP ^8.3, Laravel 12/13.
4. **Installation** — `composer require appsbd/auth`, auto-discovery note, `php artisan vendor:publish --tag=appsbd-auth-config`, env var block listing all six `APPSBD_*` vars.
5. **Quick start — Google OAuth** — controller snippet using `GoogleOAuth::redirect()` and `GoogleOAuth::callback()`, plus a `GoogleLoginSucceeded` listener stub (find-or-create user + `Auth::login`), noting the package never touches the User model.
6. **Quick start — Turnstile** — route with `->middleware('turnstile')`, `'turnstile'` validation rule example, `Turnstile::verify($token, $ip)` service example.
7. **Documentation** — table of links to every file in `docs/` (Installation, Configuration, GoogleOAuth, Turnstile, Middleware, Validation, Facades, DependencyInjection, Testing, Publishing, UpgradeGuide, Examples, VueIntegration).
8. **Testing** — `composer test`.
9. **License** — MIT.

- [ ] **Step 2: Write `CHANGELOG.md`**

```markdown
# Changelog

All notable changes to `appsbd/auth` are documented here. Follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and semver.

## [1.0.0] - 2026-07-22

### Added
- Google OAuth2 service: authorization URL, redirect/callback conveniences, code exchange, userinfo, refresh, revoke, session-backed CSRF state with stateless override.
- Cloudflare Turnstile service: `verify` / `verifyOrFail`, network-safe failure mode.
- `turnstile` middleware (JSON 422 / redirect-back) and validation rule (string + object).
- Events: `GoogleLoginSucceeded`, `GoogleLoginFailed`, `TurnstileVerified`, `TurnstileFailed`.
- Facades `GoogleOAuth` and `Turnstile`; interface singletons for DI.
- Publishable config, full documentation set including Vue 3 + Sanctum integration guide.
```

- [ ] **Step 3: Write `CONTRIBUTING.md`**

Content: fork/branch/PR flow; run `composer install` and `composer test`; all changes need tests (Pest); follow PSR-12; keep `CHANGELOG.md` updated; no breaking changes to `Contracts/` in 1.x; conventional commit style (`feat:`, `fix:`, `docs:`, `ci:`).

- [ ] **Step 4: Write `SECURITY.md`**

Content: supported versions table (1.x — supported); report vulnerabilities privately to `support@appsbd.com`, do not open public issues for security reports; response within 7 days; scope notes (this package handles OAuth secrets and captcha secrets — never log them).

- [ ] **Step 5: Verify and commit**

Run: `composer test` (still green — docs don't affect code).
```bash
git add README.md CHANGELOG.md CONTRIBUTING.md SECURITY.md
git commit -m "docs: root documentation set"
```

---

### Task 14: Package guides in docs/

**Files:**
- Create: `docs/Installation.md`, `docs/Configuration.md`, `docs/GoogleOAuth.md`, `docs/Turnstile.md`, `docs/Middleware.md`, `docs/Validation.md`, `docs/Facades.md`, `docs/DependencyInjection.md`, `docs/Testing.md`, `docs/Publishing.md`, `docs/UpgradeGuide.md`, `docs/Examples.md`

Each file is complete standalone markdown. Required content per file (write real prose + runnable code, matching the actual implemented signatures):

- [ ] **Step 1: `docs/Installation.md`** — composer require, PHP/Laravel requirements, auto-discovery (provider + aliases + middleware + rule registered automatically), publish command with tag `appsbd-auth-config`, env var checklist for both providers with example values, Google Cloud Console setup pointers (create OAuth client, authorized redirect URI must equal `APPSBD_GOOGLE_REDIRECT_URI`), Cloudflare dashboard pointers (create Turnstile widget, copy site key + secret).

- [ ] **Step 2: `docs/Configuration.md`** — full annotated copy of `config/appsbd-auth.php`; table of every key: type, env var, default, which exception is thrown when missing (`ConfigurationException` naming e.g. `appsbd-auth.google.client_id`); note that `scopes` and `input_name` are config-file-only (no env var).

- [ ] **Step 3: `docs/GoogleOAuth.md`** — the full `OAuthProviderInterface` API with signatures; state handling section (session default: random 40-char state stored under `appsbd-auth.google.state`, validated and cleared in `callback()`; stateless override: pass `$state` to both `generateAuthorizationUrl()` and `callback()`); `redirect()`/`callback()` conveniences with a complete controller example; events (`GoogleLoginSucceeded`, `GoogleLoginFailed`) with payload shapes; error mapping table (`OAuthException` for HTTP/token/state errors, `ConfigurationException` for config).

- [ ] **Step 4: `docs/Turnstile.md`** — `verify()` vs `verifyOrFail()` semantics; `CaptchaResponse` field reference; the network-failure guarantee (`internal-error`, never throws from `verify`); events; frontend widget snippet (plain HTML `<div class="cf-turnstile" data-sitekey="...">` + script tag) with pointer to VueIntegration.md for SPA usage.

- [ ] **Step 5: `docs/Middleware.md`** — applying `->middleware('turnstile')` to routes/groups; failure behavior (JSON 422 payload example with `errors` object; redirect-back with `$errors` for Blade); changing `input_name`; note the middleware verifies on every request it guards (tokens are single-use — don't apply it to GET pages).

- [ ] **Step 6: `docs/Validation.md`** — string rule `'turnstile'` and object rule `new TurnstileRule` examples in a FormRequest; custom error message via `messages()`; when to prefer rule vs middleware (rule composes with other validation, middleware rejects before validation runs).

- [ ] **Step 7: `docs/Facades.md`** — `GoogleOAuth` and `Turnstile` facade method lists; note they're optional sugar over the container singletons; testability note (`Http::fake()` still works under facades because services use the HTTP client).

- [ ] **Step 8: `docs/DependencyInjection.md`** — constructor injection of `OAuthProviderInterface` / `CaptchaProviderInterface` in controllers/listeners with code example; singleton lifecycle (config is read at first resolution); how to swap implementations in tests (`$this->app->instance(...)`).

- [ ] **Step 9: `docs/Testing.md`** — how consumers test code using this package: `Http::fake()` recipes for the Google token/userinfo endpoints and Turnstile siteverify (copy the fake payloads from this package's own tests), `Event::fake()` for login events, faking `CaptchaProviderInterface` with `$this->app->instance()`.

- [ ] **Step 10: `docs/Publishing.md`** — releasing the package: tag `v1.0.0`, Packagist submission, semver policy (contracts frozen in 1.x), how future providers get added (new service implementing `OAuthProviderInterface`/`CaptchaProviderInterface` + config block + facade — no breaking changes).

- [ ] **Step 11: `docs/UpgradeGuide.md`** — states there is nothing to upgrade yet (1.0.0 is the first release) and documents the policy: minor releases never break `Contracts/`, config keys are only added, deprecations get one minor release of warning.

- [ ] **Step 12: `docs/Examples.md`** — index page linking each file in `examples/` with a one-paragraph description of what it shows (files are created in Task 15).

- [ ] **Step 13: Verify and commit**

Run: `composer test` (still green).
```bash
git add docs
git commit -m "docs: package guides"
```

---

### Task 15: VueIntegration.md + examples/

**Files:**
- Create: `docs/VueIntegration.md`, `examples/GoogleAuthController.php`, `examples/TurnstileLoginController.php`, `examples/HandleGoogleLogin.php`, `examples/routes.php`, `examples/TurnstileWidget.vue`

- [ ] **Step 1: Write `examples/TurnstileWidget.vue`** (also embedded verbatim in VueIntegration.md)

```vue
<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  siteKey: { type: String, required: true },
  action: { type: String, default: undefined },
  theme: { type: String, default: 'auto' },
})

const emit = defineEmits(['verified', 'expired', 'errored'])

const container = ref(null)
let widgetId = null

function renderWidget() {
  widgetId = window.turnstile.render(container.value, {
    sitekey: props.siteKey,
    action: props.action,
    theme: props.theme,
    callback: (token) => emit('verified', token),
    'expired-callback': () => {
      emit('expired')
      reset()
    },
    'error-callback': () => emit('errored'),
  })
}

function reset() {
  if (widgetId !== null && window.turnstile) window.turnstile.reset(widgetId)
}

defineExpose({ reset })

onMounted(() => {
  if (window.turnstile) {
    renderWidget()
    return
  }
  const script = document.createElement('script')
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
  script.async = true
  script.onload = renderWidget
  document.head.appendChild(script)
})

onBeforeUnmount(() => {
  if (widgetId !== null && window.turnstile) window.turnstile.remove(widgetId)
})
</script>

<template>
  <div ref="container"></div>
</template>
```

- [ ] **Step 2: Write `examples/GoogleAuthController.php`**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Appsbd\Auth\Exceptions\OAuthException;
use Appsbd\Auth\Facades\GoogleOAuth;
use Illuminate\Http\RedirectResponse;

/**
 * Example controller — the package ships no routes; copy this into your app.
 * Pair with examples/routes.php and examples/HandleGoogleLogin.php.
 */
class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return GoogleOAuth::redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            GoogleOAuth::callback(); // fires GoogleLoginSucceeded — see HandleGoogleLogin listener
        } catch (OAuthException $e) {
            return redirect('/login')->withErrors(['google' => 'Google sign-in failed. Please try again.']);
        }

        return redirect()->intended('/dashboard');
    }
}
```

- [ ] **Step 3: Write `examples/HandleGoogleLogin.php`**

```php
<?php

namespace App\Listeners;

use App\Models\User;
use Appsbd\Auth\Events\GoogleLoginSucceeded;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Example listener: find-or-create the user, then log in via the session guard
 * (Sanctum SPA cookie auth uses the same web guard).
 */
class HandleGoogleLogin
{
    public function handle(GoogleLoginSucceeded $event): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => $event->user->email],
            [
                'name'     => $event->user->name ?? 'Google User',
                'password' => Str::password(32),
            ],
        );

        Auth::login($user, remember: true);
    }
}
```

- [ ] **Step 4: Write `examples/TurnstileLoginController.php`**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Example login controller for a Vue SPA posting credentials + turnstile token.
 * Route through ->middleware('turnstile'), or use the 'turnstile' rule as shown.
 */
class TurnstileLoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'                 => ['required', 'email'],
            'password'              => ['required'],
            'cf-turnstile-response' => ['required', 'turnstile'],
        ]);

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => $request->user()]);
    }
}
```

- [ ] **Step 5: Write `examples/routes.php`**

```php
<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\TurnstileLoginController;
use Illuminate\Support\Facades\Route;

// routes/web.php — session/cookie context is required for OAuth state and Sanctum SPA auth.
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

Route::post('/login', [TurnstileLoginController::class, 'login'])->name('login');
```

- [ ] **Step 6: Write `docs/VueIntegration.md`** with these sections, complete and runnable:

1. **Prerequisites (Sanctum cookie auth)** — `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, axios `withCredentials = true` + `withXSRFToken = true`, hit `/sanctum/csrf-cookie` before the first POST; code for an `api.js` axios instance.
2. **TurnstileWidget component** — the full component from Step 1, plus usage:
   ```vue
   <TurnstileWidget ref="widget" :site-key="siteKey" action="login" @verified="token = $event" />
   ```
3. **Login form example** — full `LoginForm.vue` (script setup + template): fields, widget, submit handler posting to `/login`; on 422 call `widget.value.reset()` and clear `token` (tokens are single-use).
4. **Google login — redirect flow** — button linking to `/auth/google/redirect`; note session state is handled server-side automatically.
5. **Google login — popup flow** — `window.open('/auth/google/redirect', ...)`, callback page posts `window.opener.postMessage({source: 'google-auth', ok: true}, window.origin)` then closes; SPA listens for the message and refetches `/api/user`.
6. **Backend** — reference `examples/GoogleAuthController.php`, `examples/TurnstileLoginController.php`, `examples/routes.php` and inline the route definitions.
7. **GoogleLoginSucceeded listener** — inline `examples/HandleGoogleLogin.php` and show `Event::listen(GoogleLoginSucceeded::class, HandleGoogleLogin::class)` registration in `AppServiceProvider::boot()`.
8. **Troubleshooting** — table: 401 after login (stateful domains/cookie domain mismatch), 419 (missing CSRF cookie call / `withXSRFToken`), `OAuthException` state mismatch (session driver, callback URL mismatch, opening callback in a different browser context), widget not rendering (script blocked, wrong site key, container removed before render), CORS (use same top-level domain; Sanctum SPA auth is cookie-based, not token-based).

- [ ] **Step 7: Verify and commit**

Run: `composer test` (still green).
```bash
git add docs/VueIntegration.md examples
git commit -m "docs: vue 3 + sanctum integration guide and runnable examples"
```

---

## Final verification (after all tasks)

- [ ] `composer test` — full suite green.
- [ ] Spec checklist sweep: every item in the spec's Architecture tree exists; every row of the error-handling table has a test; all 13 docs files + root docs exist.
- [ ] `git log --oneline` shows one commit per task, no attribution trailers.
