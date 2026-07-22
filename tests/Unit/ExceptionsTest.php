<?php

use Bijon\LaravelAuth\Exceptions\AuthException;
use Bijon\LaravelAuth\Exceptions\ConfigurationException;
use Bijon\LaravelAuth\Exceptions\OAuthException;
use Bijon\LaravelAuth\Exceptions\TurnstileException;
use Bijon\LaravelAuth\Support\CaptchaResponse;

it('has a common exception hierarchy', function () {
    expect(new OAuthException('x'))->toBeInstanceOf(AuthException::class)
        ->and(new ConfigurationException('x'))->toBeInstanceOf(AuthException::class);
});

it('names the missing config key', function () {
    $e = ConfigurationException::missing('laravel-auth.google.client_id');
    expect($e->getMessage())->toContain('laravel-auth.google.client_id');
});

it('carries the captcha response on turnstile failure', function () {
    $response = new CaptchaResponse(success: false, errorCodes: ['timeout-or-duplicate']);
    $e = new TurnstileException($response);
    expect($e)->toBeInstanceOf(AuthException::class)
        ->and($e->response->errorCodes)->toBe(['timeout-or-duplicate']);
});
