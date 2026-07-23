# Configuration

The full config file (`config/laravel-auth.php`):

```php
<?php

return [

    'google' => [
        // OAuth client ID from Google Cloud Console.
        'client_id'     => env('LA_GOOGLE_CLIENT_ID'),

        // OAuth client secret. Server-side only — never expose it.
        'client_secret' => env('LA_GOOGLE_CLIENT_SECRET'),

        // Must exactly match an Authorized redirect URI in Google Cloud Console.
        'redirect'      => env('LA_GOOGLE_REDIRECT_URI'),

        // Default scopes for generateAuthorizationUrl(); override per call.
        'scopes'        => ['openid', 'email', 'profile'],
    ],

    'turnstile' => [
        // Public site key rendered by the frontend widget.
        'site_key'   => env('LA_TURNSTILE_SITE_KEY'),

        // Secret key used for server-side siteverify calls.
        'secret'     => env('LA_TURNSTILE_SECRET'),

        // HTTP timeout (seconds) for the siteverify request.
        'timeout'    => env('LA_TURNSTILE_TIMEOUT', 10),

        // Request input the middleware/rule reads the token from.
        'input_name' => 'cf-turnstile-response',

        // Override the widget script URL served to the frontend (null = provider default).
        'script_url' => env('LA_TURNSTILE_SCRIPT_URL'),
    ],

    'recaptcha' => [
        // Public site key used by the frontend grecaptcha script.
        'site_key'   => env('LA_RECAPTCHA_SITE_KEY'),

        // Secret key used for server-side siteverify calls.
        'secret'     => env('LA_RECAPTCHA_SECRET'),

        // HTTP timeout (seconds) for the siteverify request.
        'timeout'    => env('LA_RECAPTCHA_TIMEOUT', 10),

        // Request input the middleware/rule reads the token from.
        'input_name' => 'g-recaptcha-response',

        // Minimum v3 score (0.0–1.0) required to pass.
        'min_score'  => env('LA_RECAPTCHA_SCORE', 0.5),

        // Expected action name; null disables the action check.
        'action'     => env('LA_RECAPTCHA_ACTION'),

        // Override the widget script URL served to the frontend (null = default, with ?render={site_key}).
        'script_url' => env('LA_RECAPTCHA_SCRIPT_URL'),
    ],

    'captcha' => [
        // Force a provider by name ('turnstile', 'recaptcha', ...). Null auto-detects:
        // the first registered provider with both site_key and secret set wins.
        'provider' => env('LA_CAPTCHA_PROVIDER'),
    ],

];
```

## Key reference

| Key | Type | Env var | Default | Missing/empty behavior |
|---|---|---|---|---|
| `google.client_id` | string | `LA_GOOGLE_CLIENT_ID` | — | `ConfigurationException` naming `laravel-auth.google.client_id` |
| `google.client_secret` | string | `LA_GOOGLE_CLIENT_SECRET` | — | `ConfigurationException` naming `laravel-auth.google.client_secret` |
| `google.redirect` | string | `LA_GOOGLE_REDIRECT_URI` | — | `ConfigurationException` naming `laravel-auth.google.redirect` |
| `google.scopes` | array | *(config only)* | `['openid', 'email', 'profile']` | falls back to the default |
| `turnstile.site_key` | string | `LA_TURNSTILE_SITE_KEY` | — | provider not auto-detected; served to your frontend via `Captcha::siteKey()` |
| `turnstile.secret` | string | `LA_TURNSTILE_SECRET` | — | `ConfigurationException` naming `laravel-auth.turnstile.secret` |
| `turnstile.timeout` | int (seconds) | `LA_TURNSTILE_TIMEOUT` | `10` | falls back to `10` |
| `turnstile.input_name` | string | *(config only)* | `cf-turnstile-response` | falls back to the default |
| `turnstile.script_url` | string | `LA_TURNSTILE_SCRIPT_URL` | Cloudflare's `api.js` | falls back to the default |
| `recaptcha.site_key` | string | `LA_RECAPTCHA_SITE_KEY` | — | provider not auto-detected; served to your frontend via `Captcha::siteKey()` |
| `recaptcha.secret` | string | `LA_RECAPTCHA_SECRET` | — | `ConfigurationException` naming `laravel-auth.recaptcha.secret` |
| `recaptcha.timeout` | int (seconds) | `LA_RECAPTCHA_TIMEOUT` | `10` | falls back to `10` |
| `recaptcha.input_name` | string | *(config only)* | `g-recaptcha-response` | falls back to the default |
| `recaptcha.min_score` | float (0.0–1.0) | `LA_RECAPTCHA_SCORE` | `0.5` | falls back to `0.5` |
| `recaptcha.action` | string | `LA_RECAPTCHA_ACTION` | `null` | action check disabled; frontend `params.action` falls back to `login` |
| `recaptcha.script_url` | string | `LA_RECAPTCHA_SCRIPT_URL` | Google's `api.js?render={site_key}` | falls back to the default |
| `captcha.provider` | string | `LA_CAPTCHA_PROVIDER` | `null` | [auto-detection](Captcha.md#automatic-provider-detection); unknown names throw `ConfigurationException` |

`ConfigurationException` is thrown lazily — at the moment a service call actually needs the value, not at boot. The message names the exact config key so misconfiguration is diagnosable from logs.

Note: `google.scopes` and the `input_name` keys are config-file-only settings; there is deliberately no env var for them.

A captcha provider is **auto-detected** when both its `site_key` and `secret` are set; Turnstile has priority over reCAPTCHA when both are configured. See [Captcha](Captcha.md) for the full detection rules.
