# Cloudflare Turnstile

`Bijon\LaravelAuth\Services\TurnstileService` implements `Bijon\LaravelAuth\Contracts\CaptchaProviderInterface`. It is one provider of the shared [captcha system](Captcha.md) — resolve it via DI, `app(CaptchaProviderInterface::class)` (when detected as active), or the `Turnstile` facade.

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
| `errorCodes` | array | Cloudflare error codes (e.g. `invalid-input-response`, `timeout-or-duplicate`), `['internal-error']` when Cloudflare responds with an HTTP error, or `['network-error']` when Cloudflare could not be reached at all |
| `hostname` | ?string | hostname the challenge was solved on |
| `challengedAt` | ?string | challenge timestamp (ISO 8601) |
| `action` | ?string | the widget `action` value, if set |
| `cdata` | ?string | custom data passed to the widget, if set |
| `provider` | ?string | which provider produced the response — `turnstile` here |
| `score` | ?float | reCAPTCHA v3 only; always `null` for Turnstile |
| `raw` | array | the provider's untouched siteverify JSON body |

`$response->failed()` is the inverse of `success`. The same DTO is returned by every [captcha provider](Captcha.md#unified-response).

## Failure semantics

- **Invalid token** → `verify()` returns a failed `CaptchaResponse` with Cloudflare's error codes. It does not throw.
- **HTTP error from Cloudflare (5xx)** → `verify()` returns a failed `CaptchaResponse` with `['internal-error']`. It does not throw.
- **Transport failure (timeout, DNS, TLS/certificate problems)** → `verify()` returns a failed `CaptchaResponse` with `['network-error']` and the underlying exception is passed to `report()`, so the real cause (e.g. `cURL error 60`) lands in the host app's log. It **never throws** for network problems — verification fails closed.
- **TLS out of the box** → requests are sent with an explicit CA bundle via `composer/ca-bundle`, which falls back to a bundled Mozilla CA file when the host has no certificate store configured (common on Windows WAMP/XAMPP). No server configuration is required.
- **`verifyOrFail()`** → same as `verify()`, but throws `Bijon\LaravelAuth\Exceptions\TurnstileException` on any failure. The exception carries the response: `$e->response`.
- **Missing secret** → `ConfigurationException` naming `laravel-auth.turnstile.secret` (a config error is a bug, so it throws from both methods).

## Events

| Event | Payload | Fired when |
|---|---|---|
| `Bijon\LaravelAuth\Events\TurnstileVerified` | `CaptchaResponse $response` | verification succeeded |
| `Bijon\LaravelAuth\Events\TurnstileFailed` | `CaptchaResponse $response` | verification failed (including network failures) |

The provider-agnostic `CaptchaVerified` / `CaptchaFailed` events (which the Turnstile events extend) fire alongside these — listen to those to cover every provider at once. See [Captcha events](Captcha.md#events-and-exceptions).

## Frontend widget (plain HTML)

```html
<form method="POST" action="/login">
    <!-- your fields -->
    <div class="cf-turnstile" data-sitekey="{{ config('laravel-auth.turnstile.site_key') }}"></div>
    <button type="submit">Log in</button>
</form>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
```

The widget injects a hidden `cf-turnstile-response` input — the same name the [middleware](Middleware.md) and [validation rule](Validation.md) read by default. For Vue 3 SPAs, use the reusable component in [VueIntegration](VueIntegration.md).

Remember: Turnstile tokens are **single-use** and expire after ~5 minutes. Reset the widget after a failed submit.
