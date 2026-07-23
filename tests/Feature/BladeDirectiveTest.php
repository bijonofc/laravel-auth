<?php

use Illuminate\Support\Facades\Blade;

it('renders the frontend config as JSON via @captchaConfig', function () {
    config()->set('laravel-auth.turnstile.site_key', 'tk');
    config()->set('laravel-auth.turnstile.secret', 'ts-secret');

    $html = Blade::render('window.app_settings = { captcha: @captchaConfig };');

    expect($html)->toBe('window.app_settings = { captcha: '.json_encode([
        'provider' => 'turnstile',
        'site_key' => 'tk',
        'input'    => 'cf-turnstile-response',
        'script'   => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
        'params'   => [],
    ]).' };');
});

it('renders literal null via @captchaConfig when no provider is configured', function () {
    expect(Blade::render('@captchaConfig'))->toBe('null');
});

it('merges directive arguments into the rendered JSON', function () {
    config()->set('laravel-auth.turnstile.site_key', 'tk');
    config()->set('laravel-auth.turnstile.secret', 'ts-secret');

    $html = Blade::render("@captchaConfig(['page' => 'login', 'theme' => 'dark'])");

    expect($html)->toContain('"page":"login"')
        ->toContain('"theme":"dark"')
        ->toContain('"provider":"turnstile"');
});

it('renders null via @captchaConfig with arguments when no provider is configured', function () {
    expect(Blade::render("@captchaConfig(['page' => 'login'])"))->toBe('null');
});
