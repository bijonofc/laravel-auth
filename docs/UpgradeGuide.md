# Upgrade Guide

## 1.1.0 — provider-based captcha system + Google reCAPTCHA v3

**No action is required.** Turnstile integrations keep working exactly as before: the `turnstile` middleware and validation rule, the `Turnstile` facade, `TurnstileService`, `TurnstileRule`, `TurnstileException`, the `TurnstileVerified`/`TurnstileFailed` events, and all `laravel-auth.turnstile.*` config keys are unchanged.

What's new:

- **Google reCAPTCHA v3** support (`RecaptchaV3Service`) with score and action checks — set `LA_RECAPTCHA_SITE_KEY` + `LA_RECAPTCHA_SECRET` and it activates via [auto-detection](Captcha.md#automatic-provider-detection).
- Provider-agnostic **`captcha` middleware and validation rule**, the **`Captcha` facade** / `CaptchaManager`, and `Captcha::extend()` for custom providers. Prefer these in new code; the `turnstile` aliases stay.
- `CaptchaResponse` gained `provider`, `score`, and `raw` properties (existing properties untouched).
- New config keys `laravel-auth.recaptcha.*` and `laravel-auth.captcha.provider`. Your published config keeps working without them; re-publish with `--force` to pick up the new commented defaults if you want them in your file.

Internal notes, only relevant if you extended the package:

- `TurnstileService` now extends the new `AbstractCaptchaService`; behavior is unchanged. `CaptchaProviderInterface` itself is untouched (contracts stay frozen).
- `TurnstileException` now extends the new `CaptchaException`, and `TurnstileVerified`/`TurnstileFailed` extend the new `CaptchaVerified`/`CaptchaFailed` — existing `catch`/listener code keeps working. Turnstile verifications now fire the generic events *in addition to* the Turnstile ones.
- `VerifyTurnstile` now extends the new `VerifyCaptcha` middleware; constructor signature and behavior are unchanged.
- `TurnstileRule` now extends the new `CaptchaRule`; behavior is unchanged.
- `app(CaptchaProviderInterface::class)` resolves through auto-detection instead of a hard alias to `TurnstileService`. With only Turnstile configured (or nothing configured) it still resolves the `TurnstileService` singleton, so existing behavior is preserved; custom rebindings of the interface still win everywhere.

## 1.0.1 — migrating from `appsbd/auth`

The package was renamed from `appsbd/auth` to `bijon/laravel-auth` (now on Packagist), the PHP namespace changed from `Appsbd\Auth` to `Bijon\LaravelAuth`, the config file from `appsbd-auth.php` to `laravel-auth.php`, and the env vars from `APPSBD_*` to `LA_*`.

**None of this is automatic.** Composer treats the new name as a different package, and your published config and `.env` files belong to your app — the package never rewrites them. In each consuming app:

### 1. Swap the composer dependency

If the app installed via a `repositories` VCS entry, update its URL to `https://github.com/bijonofc/laravel-auth.git` (or delete the entry entirely — the package is on Packagist now). Then:

```bash
composer remove appsbd/auth
composer require bijon/laravel-auth:^1.0.1
```

### 2. Update PHP imports

Find and replace across your app code (listeners, controllers, form requests):

```
Appsbd\Auth\  →  Bijon\LaravelAuth\
```

Class names themselves are unchanged — only the namespace prefix moved. If you registered the provider or facades manually instead of relying on auto-discovery, update those entries too.

### 3. Re-publish the config

```bash
rm config/appsbd-auth.php
php artisan vendor:publish --tag=laravel-auth-config
```

Port any customizations from your old published file into the new `config/laravel-auth.php`, and change any `config('appsbd-auth.…')` calls in your app to `config('laravel-auth.…')`.

### 4. Rename the env vars — on every environment

In `.env` (local, staging, production — values stay the same, only keys change):

| Old | New |
|---|---|
| `APPSBD_GOOGLE_CLIENT_ID` | `LA_GOOGLE_CLIENT_ID` |
| `APPSBD_GOOGLE_CLIENT_SECRET` | `LA_GOOGLE_CLIENT_SECRET` |
| `APPSBD_GOOGLE_REDIRECT_URI` | `LA_GOOGLE_REDIRECT_URI` |
| `APPSBD_TURNSTILE_SITE_KEY` | `LA_TURNSTILE_SITE_KEY` |
| `APPSBD_TURNSTILE_SECRET` | `LA_TURNSTILE_SECRET` |
| `APPSBD_TURNSTILE_TIMEOUT` | `LA_TURNSTILE_TIMEOUT` |

### 5. Clear caches and verify

```bash
php artisan optimize:clear
```

Then hit your Google redirect/callback routes and a Turnstile-protected form. If config is cached in production (`config:cache`), re-cache after the `.env` change.

### Behavior changes in 1.0.1

- Outbound HTTPS requests (Google, Cloudflare) now send an explicit CA bundle via `composer/ca-bundle` — fixes TLS failures on hosts without a configured certificate store (e.g. Windows WAMP/XAMPP). No action needed.
- `TurnstileService::verify()` returns `['network-error']` instead of `['internal-error']` when Cloudflare is unreachable, and reports the underlying exception to your app's log. Only relevant if you match on error codes.

## 1.0.0

Initial release — nothing to upgrade from.

## Compatibility policy

When future versions ship, this page will list every step needed to upgrade. The standing guarantees within the 1.x series:

- **Contracts are frozen.** `OAuthProviderInterface` and `CaptchaProviderInterface` will not change incompatibly.
- **Config keys are only added, never renamed or removed.** Your published `config/laravel-auth.php` keeps working; re-publish with `--force` only if you want new commented defaults.
- **Events keep their constructor signatures.** New data arrives as new public properties.
- **Deprecations warn first.** Anything slated for removal in 2.0 triggers a deprecation notice for at least one minor release beforehand.
