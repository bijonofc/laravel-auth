<?php

use Appsbd\Auth\Events\TurnstileFailed;
use Appsbd\Auth\Events\TurnstileVerified;
use Appsbd\Auth\Exceptions\ConfigurationException;
use Appsbd\Auth\Exceptions\TurnstileException;
use Appsbd\Auth\Services\TurnstileService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function turnstileService(array $overrides = []): TurnstileService
{
    return new TurnstileService(array_merge([
        'site_key' => 'sk',
        'secret'   => 'ts-secret',
        'timeout'  => 10,
    ], $overrides));
}

it('verifies a token successfully and fires TurnstileVerified', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => true, 'hostname' => 'app.test',
            'challenge_ts' => '2026-07-22T00:00:00Z', 'action' => 'login', 'cdata' => 'x',
        ]),
    ]);
    Event::fake();

    $result = turnstileService()->verify('the-token', '1.2.3.4');

    expect($result->success)->toBeTrue()
        ->and($result->hostname)->toBe('app.test')
        ->and($result->action)->toBe('login');

    Http::assertSent(fn ($request) => $request['secret'] === 'ts-secret'
        && $request['response'] === 'the-token'
        && $request['remoteip'] === '1.2.3.4');

    Event::assertDispatched(TurnstileVerified::class);
});

it('returns failure codes and fires TurnstileFailed', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => false, 'error-codes' => ['invalid-input-response'],
        ]),
    ]);
    Event::fake();

    $result = turnstileService()->verify('bad-token');

    expect($result->failed())->toBeTrue()
        ->and($result->errorCodes)->toBe(['invalid-input-response']);

    Event::assertDispatched(TurnstileFailed::class);
});

it('never throws on network failure — returns internal-error', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $result = turnstileService()->verify('any');

    expect($result->failed())->toBeTrue()
        ->and($result->errorCodes)->toBe(['internal-error']);
});

it('treats HTTP 5xx as internal-error without throwing', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response('oops', 502)]);

    $result = turnstileService()->verify('any');

    expect($result->failed())->toBeTrue()
        ->and($result->errorCodes)->toBe(['internal-error']);
});

it('verifyOrFail throws TurnstileException carrying the response', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => false, 'error-codes' => ['timeout-or-duplicate'],
        ]),
    ]);

    try {
        turnstileService()->verifyOrFail('stale');
        $this->fail('Expected TurnstileException');
    } catch (TurnstileException $e) {
        expect($e->response->errorCodes)->toBe(['timeout-or-duplicate']);
    }
});

it('verifyOrFail returns the response on success', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    expect(turnstileService()->verifyOrFail('good')->success)->toBeTrue();
});

it('throws ConfigurationException when secret is missing', function () {
    turnstileService(['secret' => null])->verify('token');
})->throws(ConfigurationException::class, 'appsbd-auth.turnstile.secret');
