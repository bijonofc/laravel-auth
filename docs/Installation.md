# Installation

## Requirements

- PHP `^8.3`
- Laravel (illuminate components) `^12.0 | ^13.0`

## Install

```bash
composer require appsbd/auth
```

Package auto-discovery registers everything for you:

- `Appsbd\Auth\Providers\AuthServiceProvider`
- Facade aliases `GoogleOAuth` and `Turnstile`
- Route middleware alias `turnstile`
- Validation rule `turnstile`

## Publish the config

```bash
php artisan vendor:publish --tag=appsbd-auth-config
```

This copies `config/appsbd-auth.php` into your app. See [Configuration](Configuration.md) for every key.

## Environment variables

```env
# Google OAuth2
APPSBD_GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
APPSBD_GOOGLE_CLIENT_SECRET=your-client-secret
APPSBD_GOOGLE_REDIRECT_URI=https://your-app.test/auth/google/callback

# Cloudflare Turnstile
APPSBD_TURNSTILE_SITE_KEY=0x4AAAAAAA...
APPSBD_TURNSTILE_SECRET=0x4AAAAAAA...
APPSBD_TURNSTILE_TIMEOUT=10
```

## Google Cloud Console setup

1. Create (or select) a project at <https://console.cloud.google.com/>.
2. **APIs & Services → Credentials → Create Credentials → OAuth client ID**, application type **Web application**.
3. Add an **Authorized redirect URI** that exactly matches `APPSBD_GOOGLE_REDIRECT_URI` (scheme, host, and path).
4. Copy the client ID and client secret into your `.env`.

## Cloudflare Turnstile setup

1. In the Cloudflare dashboard, open **Turnstile** and create a widget.
2. Add your app's hostname(s) to the widget's allowed domains.
3. Copy the **site key** (used by the frontend widget) and **secret key** (used by this package server-side) into your `.env`.

Next: [Configuration](Configuration.md) · [GoogleOAuth](GoogleOAuth.md) · [Turnstile](Turnstile.md) · [VueIntegration](VueIntegration.md)
