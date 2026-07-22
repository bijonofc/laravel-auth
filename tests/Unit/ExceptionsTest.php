<?php

use Appsbd\Auth\Exceptions\AuthException;
use Appsbd\Auth\Exceptions\ConfigurationException;
use Appsbd\Auth\Exceptions\OAuthException;
use Appsbd\Auth\Exceptions\TurnstileException;
use Appsbd\Auth\Support\CaptchaResponse;

it('has a common exception hierarchy', function () {
    expect(new OAuthException('x'))->toBeInstanceOf(AuthException::class)
        ->and(new ConfigurationException('x'))->toBeInstanceOf(AuthException::class);
});

it('names the missing config key', function () {
    $e = ConfigurationException::missing('appsbd-auth.google.client_id');
    expect($e->getMessage())->toContain('appsbd-auth.google.client_id');
});

it('carries the captcha response on turnstile failure', function () {
    $response = new CaptchaResponse(success: false, errorCodes: ['timeout-or-duplicate']);
    $e = new TurnstileException($response);
    expect($e)->toBeInstanceOf(AuthException::class)
        ->and($e->response->errorCodes)->toBe(['timeout-or-duplicate']);
});
