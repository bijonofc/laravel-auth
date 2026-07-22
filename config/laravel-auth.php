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
    ],

];
