<?php

use Bijon\LaravelAuth\Support\CaptchaResponse;
use Bijon\LaravelAuth\Support\OAuthTokens;
use Bijon\LaravelAuth\Support\OAuthUser;

it('holds oauth user data', function () {
    $user = new OAuthUser(id: '123', email: 'a@b.c', name: 'A', avatarUrl: 'https://img', raw: ['sub' => '123']);
    expect($user->id)->toBe('123')
        ->and($user->email)->toBe('a@b.c')
        ->and($user->raw)->toBe(['sub' => '123']);
});

it('holds oauth tokens with defaults', function () {
    $tokens = new OAuthTokens(accessToken: 'at', refreshToken: null, expiresIn: 3599, idToken: null);
    expect($tokens->accessToken)->toBe('at')
        ->and($tokens->tokenType)->toBe('Bearer');
});

it('reports captcha failure state', function () {
    $ok = new CaptchaResponse(success: true);
    $bad = new CaptchaResponse(success: false, errorCodes: ['invalid-input-response']);
    expect($ok->failed())->toBeFalse()
        ->and($bad->failed())->toBeTrue()
        ->and($bad->errorCodes)->toBe(['invalid-input-response']);
});
