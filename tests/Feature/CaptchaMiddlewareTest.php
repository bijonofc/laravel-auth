<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::post('/guarded', fn () => response()->json(['ok' => true]))
        ->middleware(['web', 'captcha']);
});

it('verifies through turnstile when turnstile is configured', function () {
    config()->set('laravel-auth.turnstile.site_key', 'tk');
    config()->set('laravel-auth.turnstile.secret', 'ts-secret');
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $this->post('/guarded', ['cf-turnstile-response' => 'good'])->assertOk();

    Http::assertSent(fn ($request) => $request['response'] === 'good');
});

it('verifies through recaptcha when only recaptcha is configured', function () {
    config()->set('laravel-auth.recaptcha.site_key', 'rk');
    config()->set('laravel-auth.recaptcha.secret', 'rc-secret');
    Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.9])]);

    $this->post('/guarded', ['g-recaptcha-response' => 'good'])->assertOk();

    Http::assertSent(fn ($request) => $request['response'] === 'good');
});

it('rejects a failing recaptcha token with a 422 keyed by the provider input name', function () {
    config()->set('laravel-auth.recaptcha.site_key', 'rk');
    config()->set('laravel-auth.recaptcha.secret', 'rc-secret');
    Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.1])]);

    $this->postJson('/guarded', ['g-recaptcha-response' => 'robotic'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['g-recaptcha-response']);
});

it('redirects back with a validation error for web requests', function () {
    config()->set('laravel-auth.recaptcha.site_key', 'rk');
    config()->set('laravel-auth.recaptcha.secret', 'rc-secret');
    Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => false])]);

    $this->from('/form')
        ->post('/guarded', ['g-recaptcha-response' => 'bad'])
        ->assertRedirect('/form')
        ->assertSessionHasErrors(['g-recaptcha-response']);
});

it('respects a customized recaptcha input name', function () {
    config()->set('laravel-auth.recaptcha.site_key', 'rk');
    config()->set('laravel-auth.recaptcha.secret', 'rc-secret');
    config()->set('laravel-auth.recaptcha.input_name', 'captcha_token');
    Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.9])]);

    $this->post('/guarded', ['captcha_token' => 'good'])->assertOk();

    Http::assertSent(fn ($request) => $request['response'] === 'good');
});
