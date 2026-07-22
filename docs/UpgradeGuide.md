# Upgrade Guide

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
