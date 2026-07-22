<?php

use Appsbd\Auth\Providers\AuthServiceProvider;
use Illuminate\Support\ServiceProvider;

it('merges the package config', function () {
    expect(config('appsbd-auth.turnstile.input_name'))->toBe('cf-turnstile-response')
        ->and(config('appsbd-auth.google.scopes'))->toBe(['openid', 'email', 'profile'])
        ->and((int) config('appsbd-auth.turnstile.timeout'))->toBe(10);
});

it('registers the config as publishable under the appsbd-auth-config tag', function () {
    $paths = ServiceProvider::pathsToPublish(AuthServiceProvider::class, 'appsbd-auth-config');
    expect($paths)->not->toBeEmpty();
});
