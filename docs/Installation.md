# Installation

## Requirements

- PHP `^8.3`
- Laravel (illuminate components) `^12.0 | ^13.0`

## Install

```bash
composer require bijon/laravel-auth
```

Package auto-discovery registers everything for you:

- `Bijon\LaravelAuth\Providers\AuthServiceProvider`
- Facade aliases `Captcha`, `GoogleOAuth`, and `Turnstile`
- Route middleware aliases `captcha` and `turnstile`
- Validation rules `captcha` and `turnstile`

## Publish the config

```bash
php artisan vendor:publish --tag=laravel-auth-config
```

This copies `config/laravel-auth.php` into your app. See [Configuration](Configuration.md) for every key.

## Environment variables

```env
# Google OAuth2
LA_GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
LA_GOOGLE_CLIENT_SECRET=your-client-secret
LA_GOOGLE_REDIRECT_URI=https://your-app.test/auth/google/callback

# Captcha — configure ONE provider; the package auto-detects it.
# Cloudflare Turnstile:
LA_TURNSTILE_SITE_KEY=0x4AAAAAAA...
LA_TURNSTILE_SECRET=0x4AAAAAAA...
LA_TURNSTILE_TIMEOUT=10

# ... or Google reCAPTCHA v3:
LA_RECAPTCHA_SITE_KEY=6Lc...
LA_RECAPTCHA_SECRET=6Lc...
LA_RECAPTCHA_SCORE=0.5
LA_RECAPTCHA_ACTION=login
```

If both providers are configured, Turnstile wins; force one with `LA_CAPTCHA_PROVIDER=recaptcha`. See [Captcha](Captcha.md) for the detection rules.

## Google Cloud Console setup

1. Create (or select) a project at <https://console.cloud.google.com/>.
2. **APIs & Services → Credentials → Create Credentials → OAuth client ID**, application type **Web application**.
3. Add an **Authorized redirect URI** that exactly matches `LA_GOOGLE_REDIRECT_URI` (scheme, host, and path).
4. Copy the client ID and client secret into your `.env`.

## Cloudflare Turnstile setup

1. In the Cloudflare dashboard, open **Turnstile** and create a widget.
2. Add your app's hostname(s) to the widget's allowed domains.
3. Copy the **site key** (used by the frontend widget) and **secret key** (used by this package server-side) into your `.env`.

## Google reCAPTCHA v3 setup

1. Open the [reCAPTCHA admin console](https://www.google.com/recaptcha/admin) and register a new site with type **reCAPTCHA v3**.
2. Add your app's domain(s).
3. Copy the **site key** (used by the frontend script) and **secret key** (used by this package server-side) into your `.env`.

Next: [Configuration](Configuration.md) · [GoogleOAuth](GoogleOAuth.md) · [Captcha](Captcha.md) · [Turnstile](Turnstile.md) · [Recaptcha](Recaptcha.md) · [VueIntegration](VueIntegration.md)
