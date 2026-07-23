<?php

use Bijon\LaravelAuth\Facades\Captcha;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('laravel-auth.recaptcha.site_key', 'rk');
    config()->set('laravel-auth.recaptcha.secret', 'rc-secret');
});

it('verifies through the detected provider', function () {
    Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.9])]);

    $result = Captcha::verify('token', '1.2.3.4');

    expect($result->success)->toBeTrue()
        ->and($result->provider)->toBe('recaptcha');
});

it('exposes provider metadata for the frontend', function () {
    expect(Captcha::detect())->toBe('recaptcha')
        ->and(Captcha::siteKey())->toBe('rk')
        ->and(Captcha::inputName())->toBe('g-recaptcha-response');
});

it('registers the facade alias', function () {
    expect(class_exists('Captcha'))->toBeTrue();
});
