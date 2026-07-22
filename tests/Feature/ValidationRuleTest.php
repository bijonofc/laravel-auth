<?php

use Bijon\LaravelAuth\Validation\TurnstileRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

beforeEach(fn () => config()->set('laravel-auth.turnstile.secret', 'ts-secret'));

it('passes as a rule object when verification succeeds', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $v = Validator::make(['cf-turnstile-response' => 'good'], ['cf-turnstile-response' => [new TurnstileRule]]);

    expect($v->passes())->toBeTrue();
});

it('fails as a rule object when verification fails', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $v = Validator::make(['cf-turnstile-response' => 'bad'], ['cf-turnstile-response' => [new TurnstileRule]]);

    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('cf-turnstile-response'))->toBeTrue();
});

it('works as the string rule "turnstile"', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    expect(Validator::make(['t' => 'good'], ['t' => 'turnstile'])->passes())->toBeTrue();
});

it('fails as the string rule "turnstile" when verification fails', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
    expect(Validator::make(['t' => 'bad'], ['t' => 'turnstile'])->fails())->toBeTrue();
});
