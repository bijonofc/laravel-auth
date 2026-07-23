# Captcha Provider System

The package ships one captcha system with pluggable providers. Cloudflare Turnstile and Google reCAPTCHA v3 are built in; the middleware, validation rules, manager, and response DTO are provider-agnostic, so your code looks the same whichever provider you configure.

## Automatic provider detection

You never have to name a provider. `Bijon\LaravelAuth\Services\CaptchaManager` picks the active one on every resolution:

1. If `laravel-auth.captcha.provider` (`LA_CAPTCHA_PROVIDER`) is set, that provider is used. Unknown names throw a `ConfigurationException`.
2. Otherwise the first **configured** provider wins, in registration order — a provider is configured when both its `site_key` and `secret` are set:
   1. **Turnstile** — `LA_TURNSTILE_SITE_KEY` + `LA_TURNSTILE_SECRET`
   2. **reCAPTCHA v3** — `LA_RECAPTCHA_SITE_KEY` + `LA_RECAPTCHA_SECRET`
3. If nothing is configured, Turnstile is assumed and verification throws a `ConfigurationException` naming the missing secret. Validation is **never silently bypassed**.

`Bijon\LaravelAuth\Contracts\CaptchaProviderInterface` resolves to the detected provider, so everything that consumes the contract — the `captcha`/`turnstile` middleware, the validation rules, your own injections — follows detection automatically.

## The manager

Resolve `CaptchaManager` via DI or the [`Captcha` facade](Facades.md):

```php
use Bijon\LaravelAuth\Facades\Captcha;

Captcha::verify($token, $request->ip());   // delegates to the detected provider
Captcha::verifyOrFail($token);             // throws CaptchaException on failure

Captcha::detect();                         // 'turnstile' | 'recaptcha' | ...
Captcha::siteKey();                        // the active provider's public site key
Captcha::inputName();                      // request input the frontend submits the token under
Captcha::provider('recaptcha');            // resolve a specific provider explicitly
Captcha::isConfigured('turnstile');        // both site_key and secret set?
Captcha::providers();                      // registered names, in detection priority order
```

`siteKey()` and `inputName()` exist so a controller can hand the frontend everything it needs without knowing which provider is active:

```php
Route::get('/captcha-config', fn () => [
    'provider'   => Captcha::detect(),
    'site_key'   => Captcha::siteKey(),
    'input_name' => Captcha::inputName(),
]);
```

## Unified response

Every provider returns the same `Bijon\LaravelAuth\Support\CaptchaResponse` DTO — see the field table in [Turnstile](Turnstile.md#captcharesponse-fields). Provider-specific data lands in the shared fields: `provider` names the source, `score` is set by reCAPTCHA v3 (null for Turnstile), `cdata` by Turnstile (null for reCAPTCHA), and `raw` always carries the provider's untouched siteverify JSON.

## Events and exceptions

Every provider fires the provider-agnostic events:

| Event | Fired when |
|---|---|
| `Bijon\LaravelAuth\Events\CaptchaVerified` | verification succeeded |
| `Bijon\LaravelAuth\Events\CaptchaFailed` | verification failed (including network failures) |

Turnstile *additionally* fires its legacy `TurnstileVerified` / `TurnstileFailed` events (which extend the generic ones), so pre-1.1 listeners keep working.

`verifyOrFail()` throws `Bijon\LaravelAuth\Exceptions\CaptchaException` carrying the response as `$e->response`. Turnstile throws its `TurnstileException` subclass — `catch (CaptchaException $e)` handles both.

## Adding your own provider

The core never needs modification. A new provider is three steps:

**1. Implement the provider.** Extend `AbstractCaptchaService` and you inherit the whole validation flow — secret check, TLS-safe HTTP POST, fail-closed error handling (`internal-error` / `network-error`), and event dispatch:

```php
use Bijon\LaravelAuth\Services\AbstractCaptchaService;
use Bijon\LaravelAuth\Support\CaptchaResponse;

class HcaptchaService extends AbstractCaptchaService
{
    public function name(): string
    {
        return 'hcaptcha'; // also the config key: laravel-auth.hcaptcha
    }

    protected function verifyUrl(): string
    {
        return 'https://api.hcaptcha.com/siteverify';
    }

    public function inputName(): string
    {
        return $this->config['input_name'] ?? 'h-captcha-response';
    }

    protected function mapResponse(array $data): CaptchaResponse
    {
        return new CaptchaResponse(
            success: (bool) ($data['success'] ?? false),
            errorCodes: $data['error-codes'] ?? [],
            hostname: $data['hostname'] ?? null,
            challengedAt: $data['challenge_ts'] ?? null,
            provider: $this->name(),
            raw: $data,
        );
    }
}
```

(Implementing `CaptchaProviderInterface` directly also works if the provider's flow doesn't fit the base class.)

**2. Register it** — in a service provider's `boot()`:

```php
use Bijon\LaravelAuth\Facades\Captcha;

Captcha::extend('hcaptcha', fn ($app) => new HcaptchaService(config('laravel-auth.hcaptcha', [])));
```

**3. Configure it** — add a `laravel-auth.hcaptcha` block with `site_key` and `secret`, and detection picks it up like the built-ins.

## Validation flow

All providers share it:

1. The middleware/rule reads the token from the provider's input name.
2. The provider POSTs `secret`, `response`, and `remoteip` to its siteverify endpoint (explicit CA bundle, configured timeout).
3. The JSON body is mapped to a `CaptchaResponse`; provider-specific checks (e.g. reCAPTCHA's score/action) run during mapping.
4. `CaptchaVerified` / `CaptchaFailed` fires; the caller gets the unified response. Transport errors fail closed — never an exception, never a pass.
