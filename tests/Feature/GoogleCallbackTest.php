<?php

use Bijon\LaravelAuth\Events\GoogleLoginFailed;
use Bijon\LaravelAuth\Events\GoogleLoginSucceeded;
use Bijon\LaravelAuth\Exceptions\OAuthException;
use Bijon\LaravelAuth\Services\GoogleOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function fakeGoogleHttp(): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'at-123', 'refresh_token' => 'rt-456',
            'expires_in' => 3599, 'id_token' => 'idt', 'token_type' => 'Bearer',
        ]),
        'openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'g-1', 'email' => 'user@example.com', 'name' => 'Test User', 'picture' => null,
        ]),
    ]);
}

it('redirect() returns a RedirectResponse to the authorization url', function () {
    $response = googleService()->redirect();

    expect($response)->toBeInstanceOf(Illuminate\Http\RedirectResponse::class)
        ->and($response->getTargetUrl())->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?');
});

it('callback() validates session state, returns user + tokens, fires GoogleLoginSucceeded', function () {
    fakeGoogleHttp();
    Event::fake();
    session()->put(GoogleOAuthService::STATE_SESSION_KEY, 'expected-state');

    $result = googleService()->callback(code: 'the-code', state: 'expected-state');

    expect($result['user']->email)->toBe('user@example.com')
        ->and($result['tokens']->accessToken)->toBe('at-123')
        ->and(session()->has(GoogleOAuthService::STATE_SESSION_KEY))->toBeFalse();

    Event::assertDispatched(GoogleLoginSucceeded::class, fn ($e) => $e->user->id === 'g-1' && $e->tokens->accessToken === 'at-123');
});

it('callback() reads code and state from the current request when not passed', function () {
    fakeGoogleHttp();
    session()->put(GoogleOAuthService::STATE_SESSION_KEY, 'req-state');
    $this->instance('request', Request::create('/auth/google/callback', 'GET', [
        'code' => 'the-code', 'state' => 'req-state',
    ]));

    $result = googleService()->callback();

    expect($result['user']->id)->toBe('g-1');
});

it('callback() throws and fires GoogleLoginFailed on state mismatch', function () {
    Event::fake();
    session()->put(GoogleOAuthService::STATE_SESSION_KEY, 'expected-state');

    expect(fn () => googleService()->callback(code: 'x', state: 'tampered'))
        ->toThrow(OAuthException::class);

    Event::assertDispatched(GoogleLoginFailed::class);
});

it('callback() bypasses session validation when explicit state is supplied with no session state', function () {
    fakeGoogleHttp();

    $result = googleService()->callback(code: 'the-code', state: 'client-managed-state');

    expect($result['user']->id)->toBe('g-1');
});

it('callback() throws when no state is available at all', function () {
    $this->instance('request', Request::create('/auth/google/callback', 'GET', ['code' => 'x']));

    googleService()->callback();
})->throws(OAuthException::class);

it('callback() fires GoogleLoginFailed when the token exchange fails', function () {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);
    Event::fake();

    expect(fn () => googleService()->callback(code: 'bad', state: 'client-state'))
        ->toThrow(OAuthException::class);

    Event::assertDispatched(GoogleLoginFailed::class, fn ($e) => $e->exception instanceof OAuthException);
});
