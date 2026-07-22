<?php

use Bijon\LaravelAuth\Events\TurnstileFailed;
use Bijon\LaravelAuth\Events\TurnstileVerified;
use Bijon\LaravelAuth\Exceptions\ConfigurationException;
use Bijon\LaravelAuth\Exceptions\TurnstileException;
use Bijon\LaravelAuth\Services\TurnstileService;
use Composer\CaBundle\CaBundle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
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

it('never throws on network failure — returns network-error', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $result = turnstileService()->verify('any');

    expect($result->failed())->toBeTrue()
        ->and($result->errorCodes)->toBe(['network-error']);
});

it('reports the transport exception so it reaches the host app log', function () {
    Exceptions::fake();
    Http::fake(fn () => throw new ConnectionException('cURL error 60: SSL certificate problem'));

    turnstileService()->verify('any');

    Exceptions::assertReported(ConnectionException::class);
});

it('sends the siteverify request with the CA bundle verify option', function () {
    $captured = null;
    Http::fake(function ($request, $options) use (&$captured) {
        $captured = $options;

        return Http::response(['success' => true]);
    });

    turnstileService()->verify('the-token');

    expect($captured['verify'] ?? null)->toBe(CaBundle::getSystemCaRootBundlePath())
        ->and(file_exists($captured['verify']))->toBeTrue();
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
})->throws(ConfigurationException::class, 'laravel-auth.turnstile.secret');
