<?php

use Bijon\LaravelAuth\Exceptions\ConfigurationException;
use Bijon\LaravelAuth\Services\GoogleOAuthService;

it('generates an authorization url with default scopes and stores random state in the session', function () {
    $url = googleService()->generateAuthorizationUrl();

    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?')
        ->and($query['client_id'])->toBe('cid')
        ->and($query['redirect_uri'])->toBe('https://app.test/auth/google/callback')
        ->and($query['response_type'])->toBe('code')
        ->and($query['scope'])->toBe('openid email profile')
        ->and(strlen($query['state']))->toBe(40)
        ->and(session()->get(GoogleOAuthService::STATE_SESSION_KEY))->toBe($query['state']);
});

it('uses explicit state without touching the session', function () {
    $url = googleService()->generateAuthorizationUrl(state: 'my-custom-state');

    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($query['state'])->toBe('my-custom-state')
        ->and(session()->has(GoogleOAuthService::STATE_SESSION_KEY))->toBeFalse();
});

it('lets explicit scopes override config scopes', function () {
    $url = googleService()->generateAuthorizationUrl(scopes: ['email']);

    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('email');
});

it('throws a ConfigurationException naming the key when client_id is missing', function () {
    googleService(['client_id' => null])->generateAuthorizationUrl();
})->throws(ConfigurationException::class, 'laravel-auth.google.client_id');

it('throws a ConfigurationException naming the key when redirect is missing', function () {
    googleService(['redirect' => ''])->generateAuthorizationUrl();
})->throws(ConfigurationException::class, 'laravel-auth.google.redirect');
