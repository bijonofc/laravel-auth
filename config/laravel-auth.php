<?php

return [

    'google' => [
        'client_id'     => env('LA_GOOGLE_CLIENT_ID'),
        'client_secret' => env('LA_GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('LA_GOOGLE_REDIRECT_URI'),
        'scopes'        => ['openid', 'email', 'profile'],
    ],

    'turnstile' => [
        'site_key'   => env('LA_TURNSTILE_SITE_KEY'),
        'secret'     => env('LA_TURNSTILE_SECRET'),
        'timeout'    => env('LA_TURNSTILE_TIMEOUT', 10),
        'input_name' => 'cf-turnstile-response',
        // Override the widget script URL served to the frontend (null = provider default).
        'script_url' => env('LA_TURNSTILE_SCRIPT_URL'),
    ],

    'recaptcha' => [
        'site_key'   => env('LA_RECAPTCHA_SITE_KEY'),
        'secret'     => env('LA_RECAPTCHA_SECRET'),
        'timeout'    => env('LA_RECAPTCHA_TIMEOUT', 10),
        'input_name' => 'g-recaptcha-response',
        'min_score'  => env('LA_RECAPTCHA_SCORE', 0.5),
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
