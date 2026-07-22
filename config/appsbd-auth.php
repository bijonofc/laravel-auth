<?php

return [

    'google' => [
        'client_id'     => env('APPSBD_GOOGLE_CLIENT_ID'),
        'client_secret' => env('APPSBD_GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('APPSBD_GOOGLE_REDIRECT_URI'),
        'scopes'        => ['openid', 'email', 'profile'],
    ],

    'turnstile' => [
        'site_key'   => env('APPSBD_TURNSTILE_SITE_KEY'),
        'secret'     => env('APPSBD_TURNSTILE_SECRET'),
        'timeout'    => env('APPSBD_TURNSTILE_TIMEOUT', 10),
        'input_name' => 'cf-turnstile-response',
    ],

];
