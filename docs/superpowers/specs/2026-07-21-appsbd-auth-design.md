# appsbd/auth — Design Spec (v1.0)

Date: 2026-07-21
Status: Approved by user (pre-implementation)
Source requirements: `appsbd-auth.md` (repo root)

## Purpose

A reusable Composer package centralizing authentication-related integrations for
Appsbd products. v1.0 ships **Google OAuth2** and **Cloudflare Turnstile**,
architected so future providers (Microsoft, GitHub, Facebook, Apple, LinkedIn,
reCAPTCHA v2/v3, hCaptcha, OTP, Passkeys) can be added without breaking APIs.

Primary consumers: **Vue 3 SPA + Laravel 13 apps using Sanctum cookie auth**.
Documentation must fully cover that integration path.

## Decisions (locked)

| Decision | Choice |
|---|---|
| OAuth engine | Hand-rolled on Laravel HTTP Client — no Socialite dependency |
| HTTP endpoints | **Services only** — the package ships no routes or controllers; consuming apps write their own |
| User handling | Package returns typed DTOs and fires events; it never touches the app's User model. Apps listen to `GoogleLoginSucceeded` and do their own find-or-create + Sanctum login |
| OAuth state (CSRF) | Session-backed by default with automatic generate/store/validate; explicit override to pass custom state for stateless setups |
| Vue support | No npm package — `docs/VueIntegration.md` with copy-paste Vue 3 examples |

## Package basics

- Name: `appsbd/auth`, namespace `Appsbd\Auth`, PSR-4 from `src/`
- PHP `^8.3`; `illuminate/support|http|validation|session|contracts` `^12.0|^13.0`
- License: MIT
- Laravel auto-discovery registers: service provider, facade aliases
  (`GoogleOAuth`, `Turnstile`), middleware alias (`turnstile`), validation rule
- Publishable config: `php artisan vendor:publish --tag=appsbd-auth-config`
- Dev deps: `pestphp/pest`, `orchestra/testbench` (Laravel 12/13 compatible)

## Configuration (`config/appsbd-auth.php`)

```php
'google' => [
    'client_id'     => env('APPSBD_GOOGLE_CLIENT_ID'),
    'client_secret' => env('APPSBD_GOOGLE_CLIENT_SECRET'),
    'redirect'      => env('APPSBD_GOOGLE_REDIRECT_URI'),
    'scopes'        => ['openid', 'email', 'profile'],
],
'turnstile' => [
    'site_key'   => env('APPSBD_TURNSTILE_SITE_KEY'),
    'secret'     => env('APPSBD_TURNSTILE_SECRET'),
    'timeout'    => env('APPSBD_TURNSTILE_TIMEOUT', 10), // seconds
    'input_name' => 'cf-turnstile-response',
],
```

Missing/empty required config throws `ConfigurationException` naming the key.

## Architecture

```
src/
  Contracts/
    OAuthProviderInterface.php
    CaptchaProviderInterface.php
  Support/                 # readonly DTOs
    OAuthUser.php          # id, email, name, avatarUrl, raw (array)
    OAuthTokens.php        # accessToken, refreshToken, expiresIn, idToken, tokenType
    CaptchaResponse.php    # success, errorCodes, hostname, challengedAt, action, cdata
  Services/
    GoogleOAuthService.php # implements OAuthProviderInterface
    TurnstileService.php   # implements CaptchaProviderInterface
  Providers/
    AuthServiceProvider.php
  Facades/
    GoogleOAuth.php
    Turnstile.php
  Http/Middleware/
    VerifyTurnstile.php
  Validation/
    TurnstileRule.php
  Events/
    GoogleLoginSucceeded.php   # (OAuthUser, OAuthTokens)
    GoogleLoginFailed.php      # (reason/exception context)
    TurnstileVerified.php      # (CaptchaResponse)
    TurnstileFailed.php        # (CaptchaResponse|error codes)
  Exceptions/
    AuthException.php          # base
    OAuthException.php
    TurnstileException.php
    ConfigurationException.php
config/appsbd-auth.php
tests/            # Pest + Testbench
docs/             # see Documentation section
examples/         # runnable controller/listener snippets
```

### Contracts

```php
interface OAuthProviderInterface {
    public function generateAuthorizationUrl(?string $state = null, array $scopes = []): string;
    public function getTokensFromCode(string $code): OAuthTokens;
    public function getUserFromAccessToken(string $accessToken): OAuthUser;
    public function refreshToken(string $refreshToken): OAuthTokens;
    public function revokeToken(string $token): bool;
}

interface CaptchaProviderInterface {
    public function verify(string $token, ?string $ip = null): CaptchaResponse;
    public function verifyOrFail(string $token, ?string $ip = null): CaptchaResponse;
}
```

### GoogleOAuthService

- Endpoints: Google OAuth2 authorize, token, userinfo (OpenID), revoke.
- `redirect()` convenience returns a `RedirectResponse` to the authorization URL.
- `callback(?string $code = null, ?string $state = null)` convenience: reads
  code/state from the current request when not passed, validates state,
  exchanges code, fetches user, fires `GoogleLoginSucceeded` /
  `GoogleLoginFailed`, returns `['user' => OAuthUser, 'tokens' => OAuthTokens]`.
- **State handling:** when `$state` is null in `generateAuthorizationUrl()`, a
  random state is generated and stored in the session; `callback()` validates
  against the session value and throws `OAuthException` on mismatch. When the
  caller supplies `$state` explicitly (both sides), the session is bypassed —
  supports stateless/token-only APIs.
- All Google HTTP errors mapped to `OAuthException` with meaningful messages;
  no raw client exceptions leak.

### TurnstileService

- `verify()` POSTs to Cloudflare siteverify with configurable timeout; returns
  `CaptchaResponse`. Network failures (timeout, 5xx) return a failed
  `CaptchaResponse` with `internal-error` code — they never throw.
- `verifyOrFail()` throws `TurnstileException` (carrying the response) on failure.
- Fires `TurnstileVerified` / `TurnstileFailed`.

### Middleware `VerifyTurnstile` (alias `turnstile`)

- Reads token from configurable `input_name` (default `cf-turnstile-response`).
- On failure: JSON 422 with error payload when `expectsJson()`, otherwise
  redirect back with validation error.

### Validation rule

- Usable as string rule `'turnstile'` and rule object `new TurnstileRule()`.
- Registered by the service provider — works immediately after install.

### Container & facades

- Interfaces bound to implementations as singletons;
  `GoogleOAuth` / `Turnstile` facades resolve those bindings.
- Everything injectable via constructor DI; facades are optional sugar.

## Error handling summary

| Failure | Behavior |
|---|---|
| Missing config key | `ConfigurationException` naming the key |
| Google HTTP/token error | `OAuthException` + `GoogleLoginFailed` event |
| State mismatch | `OAuthException` |
| Turnstile token invalid | failed `CaptchaResponse`; `verifyOrFail` throws `TurnstileException` |
| Turnstile network error | failed `CaptchaResponse` with `internal-error` (no throw from `verify`) |

## Testing

Pest + Orchestra Testbench; `Http::fake()` for every external call. Coverage:

- GoogleOAuthService: URL generation (scopes, state), code exchange, userinfo
  mapping, refresh, revoke, error mapping, session-state validate + override path
- TurnstileService: success, failure codes, timeout/network path, verifyOrFail
- Middleware: pass, JSON 422, redirect-back
- Validation rule: string + object usage
- Service provider: bindings, facade resolution, config merge, alias registration
- ConfigurationException paths

CI: GitHub Actions matrix — PHP 8.3/8.4 × Laravel 12/13.

## Documentation

Root: `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `LICENSE`, `SECURITY.md`.

`docs/`: Installation, Configuration, GoogleOAuth, Turnstile, Middleware,
Validation, Facades, DependencyInjection, Testing, Publishing, UpgradeGuide,
Examples, **VueIntegration**.

`VueIntegration.md` (first-class, per primary use case):

1. Sanctum cookie-auth prerequisites (CSRF cookie, stateful domains)
2. Reusable `<TurnstileWidget>` Vue 3 component — script loading, token emit,
   reset on failure/expiry
3. Login form example posting through the `turnstile` middleware/rule
4. Google login — redirect flow and popup flow variants
5. Backend controller examples using the services (since no routes ship)
6. `GoogleLoginSucceeded` listener example: find-or-create User + session login
7. Troubleshooting: 401/419, state mismatch, widget not rendering, CORS

## Implementation order (from requirements, step-gated)

1. Skeleton + composer.json → 2. Service provider → 3. Contracts + DTOs →
4. GoogleOAuthService → 5. TurnstileService → 6. Middleware → 7. Validation
rule → 8. Events/exceptions polish → 9. Tests → 10. Documentation + examples.
Each step must pass `composer test` (or at minimum parse/bind) before the next.

## Out of scope for v1.0

- Shipped routes/controllers, user persistence, npm package, other providers,
  translations, views. Architecture leaves room for all of these.
