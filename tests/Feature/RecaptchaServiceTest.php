<?php

use Bijon\LaravelAuth\Events\CaptchaFailed;
use Bijon\LaravelAuth\Events\CaptchaVerified;
use Bijon\LaravelAuth\Exceptions\CaptchaException;
use Bijon\LaravelAuth\Exceptions\ConfigurationException;
use Bijon\LaravelAuth\Services\RecaptchaV3Service;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function recaptchaService(array $overrides = []): RecaptchaV3Service
{
    return new RecaptchaV3Service(array_merge([
        'site_key'  => 'rk',
        'secret'    => 'rc-secret',
        'timeout'   => 10,
        'min_score' => 0.5,
        'action'    => null,
    ], $overrides));
}

it('verifies a token successfully and fires CaptchaVerified', function () {
    Http::fake([
        'www.google.com/recaptcha/*' => Http::response([
            'success' => true, 'score' => 0.9, 'action' => 'login',
            'hostname' => 'app.test', 'challenge_ts' => '2026-07-23T00:00:00Z',
        ]),
    ]);
    Event::fake();

    $result = recaptchaService()->verify('the-token', '1.2.3.4');

    expect($result->success)->toBeTrue()
        ->and($result->provider)->toBe('recaptcha')
        ->and($result->score)->toBe(0.9)
        ->and($result->action)->toBe('login')
        ->and($result->hostname)->toBe('app.test')
        ->and($result->challengedAt)->toBe('2026-07-23T00:00:00Z');

    Http::assertSent(fn ($request) => $request['secret'] === 'rc-secret'
        && $request['response'] === 'the-token'
        && $request['remoteip'] === '1.2.3.4');

    Event::assertDispatched(CaptchaVerified::class, fn ($e) => $e->response->provider === 'recaptcha');
});

it('returns failure codes and fires CaptchaFailed', function () {
    Http::fake([
        'www.google.com/recaptcha/*' => Http::response([
            'success' => false, 'error-codes' => ['invalid-input-response'],
        ]),
    ]);
    Event::fake();

    $result = recaptchaService()->verify('bad-token');

    expect($result->failed())->toBeTrue()
        ->and($result->errorCodes)->toBe(['invalid-input-response']);

    Event::assertDispatched(CaptchaFailed::class);
});

it('fails with low-score when the score is below the minimum', function () {
    Http::fake([
        'www.google.com/recaptcha/*' => Http::response([
            'success' => true, 'score' => 0.2, 'action' => 'login',
        ]),
    ]);

    $result = recaptchaService(['min_score' => 0.7])->verify('token');

    expect($result->failed())->toBeTrue()
        ->and($result->errorCodes)->toBe(['low-score'])
        ->and($result->score)->toBe(0.2);
});

it('passes when the score equals the minimum', function () {
    Http::fake([
        'www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.5]),
    ]);

    expect(recaptchaService(['min_score' => 0.5])->verify('token')->success)->toBeTrue();
});

it('fails closed with low-score when the response carries no score', function () {
    Http::fake([
        'www.google.com/recaptcha/*' => Http::response(['success' => true]),
    ]);

    $result = recaptchaService()->verify('token');

    expect($result->failed())->toBeTrue()
        ->and($result->errorCodes)->toBe(['low-score'])
        ->and($result->score)->toBeNull();
});

it('fails with action-mismatch when the action differs from the expected one', function () {
    Http::fake([
        'www.google.com/recaptcha/*' => Http::response([
            'success' => true, 'score' => 0.9, 'action' => 'signup',
        ]),
    ]);

    $result = recaptchaService(['action' => 'login'])->verify('token');

    expect($result->failed())->toBeTrue()
        ->and($result->errorCodes)->toBe(['action-mismatch'])
        ->and($result->action)->toBe('signup');
});

it('does not enforce the action when none is configured', function () {
    Http::fake([
        'www.google.com/recaptcha/*' => Http::response([
            'success' => true, 'score' => 0.9, 'action' => 'whatever',
        ]),
    ]);

    expect(recaptchaService(['action' => null])->verify('token')->success)->toBeTrue();
});

it('never throws on network failure — returns network-error', function () {
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('timeout'));

    $result = recaptchaService()->verify('any');

    expect($result->failed())->toBeTrue()
        ->and($result->errorCodes)->toBe(['network-error'])
        ->and($result->provider)->toBe('recaptcha');
});

it('treats HTTP 5xx as internal-error without throwing', function () {
    Http::fake(['www.google.com/recaptcha/*' => Http::response('oops', 502)]);

    $result = recaptchaService()->verify('any');

    expect($result->failed())->toBeTrue()
        ->and($result->errorCodes)->toBe(['internal-error']);
});

it('verifyOrFail throws CaptchaException carrying the response', function () {
    Http::fake([
        'www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.1]),
    ]);

    try {
        recaptchaService()->verifyOrFail('robotic');
        $this->fail('Expected CaptchaException');
    } catch (CaptchaException $e) {
        expect($e->response->errorCodes)->toBe(['low-score']);
    }
});

it('throws ConfigurationException when secret is missing', function () {
    recaptchaService(['secret' => null])->verify('token');
})->throws(ConfigurationException::class, 'laravel-auth.recaptcha.secret');
