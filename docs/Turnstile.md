# Cloudflare Turnstile

`Appsbd\Auth\Services\TurnstileService` implements `Appsbd\Auth\Contracts\CaptchaProviderInterface`. Resolve it via DI, `app(CaptchaProviderInterface::class)`, or the `Turnstile` facade.

## API

```php
public function verify(string $token, ?string $ip = null): CaptchaResponse;
public function verifyOrFail(string $token, ?string $ip = null): CaptchaResponse; // throws TurnstileException on failure
```

`verify()` POSTs the token (and optionally the client IP) to Cloudflare's siteverify endpoint with the configured timeout.

## `CaptchaResponse` fields

| Field | Type | Meaning |
|---|---|---|
| `success` | bool | verification passed |
| `errorCodes` | array | Cloudflare error codes (e.g. `invalid-input-response`, `timeout-or-duplicate`) or `['internal-error']` on network failure |
| `hostname` | ?string | hostname the challenge was solved on |
| `challengedAt` | ?string | challenge timestamp (ISO 8601) |
| `action` | ?string | the widget `action` value, if set |
| `cdata` | ?string | custom data passed to the widget, if set |

`$response->failed()` is the inverse of `success`.

## Failure semantics

- **Invalid token** → `verify()` returns a failed `CaptchaResponse` with Cloudflare's error codes. It does not throw.
- **Network failure (timeout, DNS, 5xx)** → `verify()` returns a failed `CaptchaResponse` with `['internal-error']`. It **never throws** for network problems — verification fails closed.
- **`verifyOrFail()`** → same as `verify()`, but throws `Appsbd\Auth\Exceptions\TurnstileException` on any failure. The exception carries the response: `$e->response`.
- **Missing secret** → `ConfigurationException` naming `appsbd-auth.turnstile.secret` (a config error is a bug, so it throws from both methods).

## Events

| Event | Payload | Fired when |
|---|---|---|
| `Appsbd\Auth\Events\TurnstileVerified` | `CaptchaResponse $response` | verification succeeded |
| `Appsbd\Auth\Events\TurnstileFailed` | `CaptchaResponse $response` | verification failed (including network failures) |

## Frontend widget (plain HTML)

```html
<form method="POST" action="/login">
    <!-- your fields -->
    <div class="cf-turnstile" data-sitekey="{{ config('appsbd-auth.turnstile.site_key') }}"></div>
    <button type="submit">Log in</button>
</form>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
```

The widget injects a hidden `cf-turnstile-response` input — the same name the [middleware](Middleware.md) and [validation rule](Validation.md) read by default. For Vue 3 SPAs, use the reusable component in [VueIntegration](VueIntegration.md).

Remember: Turnstile tokens are **single-use** and expire after ~5 minutes. Reset the widget after a failed submit.
