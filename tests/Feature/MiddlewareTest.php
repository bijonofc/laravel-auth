<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('appsbd-auth.turnstile.secret', 'ts-secret');

    Route::post('/protected', fn () => response()->json(['ok' => true]))
        ->middleware(['web', 'turnstile']);
});

it('passes the request through on successful verification', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $this->post('/protected', ['cf-turnstile-response' => 'good'])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('returns JSON 422 with an error payload for json requests', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    $this->postJson('/protected', ['cf-turnstile-response' => 'bad'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cf-turnstile-response']);
});

it('redirects back with a validation error for web requests', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $this->from('/form')
        ->post('/protected', ['cf-turnstile-response' => 'bad'])
        ->assertRedirect('/form')
        ->assertSessionHasErrors(['cf-turnstile-response']);
});

it('reads the token from a configurable input name', function () {
    config()->set('appsbd-auth.turnstile.input_name', 'captcha_token');
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $this->post('/protected', ['captcha_token' => 'good'])->assertOk();

    Http::assertSent(fn ($request) => $request['response'] === 'good');
});
