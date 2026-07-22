<?php

use Bijon\LaravelAuth\Contracts\CaptchaProviderInterface;
use Bijon\LaravelAuth\Contracts\OAuthProviderInterface;
use Bijon\LaravelAuth\Facades\GoogleOAuth;
use Bijon\LaravelAuth\Services\GoogleOAuthService;
use Bijon\LaravelAuth\Services\TurnstileService;

it('binds interfaces to singleton implementations', function () {
    expect(app(OAuthProviderInterface::class))->toBeInstanceOf(GoogleOAuthService::class)
        ->and(app(CaptchaProviderInterface::class))->toBeInstanceOf(TurnstileService::class)
        ->and(app(GoogleOAuthService::class))->toBe(app(OAuthProviderInterface::class))
        ->and(app(TurnstileService::class))->toBe(app(CaptchaProviderInterface::class));
});

it('resolves services through facades using app config', function () {
    config()->set('laravel-auth.google.client_id', 'cid');
    config()->set('laravel-auth.google.redirect', 'https://app.test/cb');

    $url = GoogleOAuth::generateAuthorizationUrl(state: 's');

    expect($url)->toContain('client_id=cid');
});

it('registers the facade aliases', function () {
    expect(class_exists('GoogleOAuth'))->toBeTrue()
        ->and(class_exists('Turnstile'))->toBeTrue();
});
