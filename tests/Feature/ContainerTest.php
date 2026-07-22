<?php

use Appsbd\Auth\Contracts\CaptchaProviderInterface;
use Appsbd\Auth\Contracts\OAuthProviderInterface;
use Appsbd\Auth\Facades\GoogleOAuth;
use Appsbd\Auth\Services\GoogleOAuthService;
use Appsbd\Auth\Services\TurnstileService;

it('binds interfaces to singleton implementations', function () {
    expect(app(OAuthProviderInterface::class))->toBeInstanceOf(GoogleOAuthService::class)
        ->and(app(CaptchaProviderInterface::class))->toBeInstanceOf(TurnstileService::class)
        ->and(app(GoogleOAuthService::class))->toBe(app(OAuthProviderInterface::class))
        ->and(app(TurnstileService::class))->toBe(app(CaptchaProviderInterface::class));
});

it('resolves services through facades using app config', function () {
    config()->set('appsbd-auth.google.client_id', 'cid');
    config()->set('appsbd-auth.google.redirect', 'https://app.test/cb');

    $url = GoogleOAuth::generateAuthorizationUrl(state: 's');

    expect($url)->toContain('client_id=cid');
});

it('registers the facade aliases', function () {
    expect(class_exists('GoogleOAuth'))->toBeTrue()
        ->and(class_exists('Turnstile'))->toBeTrue();
});
