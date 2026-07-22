<?php

use Bijon\LaravelAuth\Exceptions\OAuthException;
use Composer\CaBundle\CaBundle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('exchanges an authorization code for tokens', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token'  => 'at-123',
            'refresh_token' => 'rt-456',
            'expires_in'    => 3599,
            'id_token'      => 'idt-789',
            'token_type'    => 'Bearer',
        ]),
    ]);

    $tokens = googleService()->getTokensFromCode('the-code');

    expect($tokens->accessToken)->toBe('at-123')
        ->and($tokens->refreshToken)->toBe('rt-456')
        ->and($tokens->expiresIn)->toBe(3599)
        ->and($tokens->idToken)->toBe('idt-789');

    Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'the-code'
        && $request['client_id'] === 'cid'
        && $request['client_secret'] === 'secret');
});

it('sends the token request with the CA bundle verify option', function () {
    $captured = null;
    Http::fake(function ($request, $options) use (&$captured) {
        $captured = $options;

        return Http::response(['access_token' => 'at-123']);
    });

    googleService()->getTokensFromCode('the-code');

    expect($captured['verify'] ?? null)->toBe(CaBundle::getSystemCaRootBundlePath())
        ->and(file_exists($captured['verify']))->toBeTrue();
});

it('maps google token errors to OAuthException with the google message', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'error'             => 'invalid_grant',
            'error_description' => 'Malformed auth code.',
        ], 400),
    ]);

    googleService()->getTokensFromCode('bad-code');
})->throws(OAuthException::class, 'Malformed auth code.');

it('maps connection failures to OAuthException', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28'));

    googleService()->getTokensFromCode('any');
})->throws(OAuthException::class);

it('fetches the user profile with a bearer token', function () {
    Http::fake([
        'openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub'     => 'g-1',
            'email'   => 'user@example.com',
            'name'    => 'Test User',
            'picture' => 'https://img.example/p.png',
        ]),
    ]);

    $user = googleService()->getUserFromAccessToken('at-123');

    expect($user->id)->toBe('g-1')
        ->and($user->email)->toBe('user@example.com')
        ->and($user->name)->toBe('Test User')
        ->and($user->avatarUrl)->toBe('https://img.example/p.png')
        ->and($user->raw['sub'])->toBe('g-1');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer at-123'));
});

it('refreshes tokens and keeps the old refresh token when google omits it', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'new-at',
            'expires_in'   => 3599,
            'token_type'   => 'Bearer',
        ]),
    ]);

    $tokens = googleService()->refreshToken('rt-456');

    expect($tokens->accessToken)->toBe('new-at')
        ->and($tokens->refreshToken)->toBe('rt-456');

    Http::assertSent(fn ($request) => $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'rt-456');
});

it('revokes a token successfully', function () {
    Http::fake(['oauth2.googleapis.com/revoke' => Http::response([], 200)]);
    expect(googleService()->revokeToken('at-123'))->toBeTrue();
});

it('reports a failed revocation as false', function () {
    Http::fake(['oauth2.googleapis.com/revoke' => Http::response([], 400)]);
    expect(googleService()->revokeToken('expired'))->toBeFalse();
});

it('throws OAuthException when the token response has no access_token', function () {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['token_type' => 'Bearer'])]);

    googleService()->getTokensFromCode('code');
})->throws(OAuthException::class);
