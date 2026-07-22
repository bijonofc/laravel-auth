<?php

use Bijon\LaravelAuth\Providers\AuthServiceProvider;
use Illuminate\Support\ServiceProvider;

it('merges the package config', function () {
    expect(config('laravel-auth.turnstile.input_name'))->toBe('cf-turnstile-response')
        ->and(config('laravel-auth.google.scopes'))->toBe(['openid', 'email', 'profile'])
        ->and((int) config('laravel-auth.turnstile.timeout'))->toBe(10);
});

it('registers the config as publishable under the laravel-auth-config tag', function () {
    $paths = ServiceProvider::pathsToPublish(AuthServiceProvider::class, 'laravel-auth-config');
    expect($paths)->not->toBeEmpty();
});
