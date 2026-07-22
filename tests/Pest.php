<?php

use Appsbd\Auth\Services\GoogleOAuthService;

uses(Appsbd\Auth\Tests\TestCase::class)->in(__DIR__);

function googleService(array $overrides = []): GoogleOAuthService
{
    return new GoogleOAuthService(array_merge([
        'client_id'     => 'cid',
        'client_secret' => 'secret',
        'redirect'      => 'https://app.test/auth/google/callback',
        'scopes'        => ['openid', 'email', 'profile'],
    ], $overrides));
}
