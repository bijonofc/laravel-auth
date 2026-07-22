# Configuration

The full config file (`config/appsbd-auth.php`):

```php
<?php

return [

    'google' => [
        // OAuth client ID from Google Cloud Console.
        'client_id'     => env('APPSBD_GOOGLE_CLIENT_ID'),

        // OAuth client secret. Server-side only — never expose it.
        'client_secret' => env('APPSBD_GOOGLE_CLIENT_SECRET'),

        // Must exactly match an Authorized redirect URI in Google Cloud Console.
        'redirect'      => env('APPSBD_GOOGLE_REDIRECT_URI'),

        // Default scopes for generateAuthorizationUrl(); override per call.
        'scopes'        => ['openid', 'email', 'profile'],
    ],

    'turnstile' => [
        // Public site key rendered by the frontend widget.
        'site_key'   => env('APPSBD_TURNSTILE_SITE_KEY'),

        // Secret key used for server-side siteverify calls.
        'secret'     => env('APPSBD_TURNSTILE_SECRET'),

        // HTTP timeout (seconds) for the siteverify request.
        'timeout'    => env('APPSBD_TURNSTILE_TIMEOUT', 10),

        // Request input the middleware/rule reads the token from.
        'input_name' => 'cf-turnstile-response',
    ],

];
```

## Key reference

| Key | Type | Env var | Default | Missing/empty behavior |
|---|---|---|---|---|
| `google.client_id` | string | `APPSBD_GOOGLE_CLIENT_ID` | — | `ConfigurationException` naming `appsbd-auth.google.client_id` |
| `google.client_secret` | string | `APPSBD_GOOGLE_CLIENT_SECRET` | — | `ConfigurationException` naming `appsbd-auth.google.client_secret` |
| `google.redirect` | string | `APPSBD_GOOGLE_REDIRECT_URI` | — | `ConfigurationException` naming `appsbd-auth.google.redirect` |
| `google.scopes` | array | *(config only)* | `['openid', 'email', 'profile']` | falls back to the default |
| `turnstile.site_key` | string | `APPSBD_TURNSTILE_SITE_KEY` | — | only used by your frontend; the package never reads it server-side |
| `turnstile.secret` | string | `APPSBD_TURNSTILE_SECRET` | — | `ConfigurationException` naming `appsbd-auth.turnstile.secret` |
| `turnstile.timeout` | int (seconds) | `APPSBD_TURNSTILE_TIMEOUT` | `10` | falls back to `10` |
| `turnstile.input_name` | string | *(config only)* | `cf-turnstile-response` | falls back to the default |

`ConfigurationException` is thrown lazily — at the moment a service call actually needs the value, not at boot. The message names the exact config key so misconfiguration is diagnosable from logs.

Note: `google.scopes` and `turnstile.input_name` are config-file-only settings; there is deliberately no env var for them.
