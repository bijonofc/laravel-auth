<?php

use Bijon\LaravelAuth\Validation\CaptchaRule;
use Bijon\LaravelAuth\Validation\TurnstileRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    config()->set('laravel-auth.recaptcha.site_key', 'rk');
    config()->set('laravel-auth.recaptcha.secret', 'rc-secret');
});

it('passes as a rule object when verification succeeds', function () {
    Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.9])]);

    $v = Validator::make(['g-recaptcha-response' => 'good'], ['g-recaptcha-response' => [new CaptchaRule]]);

    expect($v->passes())->toBeTrue();
});

it('fails as a rule object when verification fails', function () {
    Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.1])]);

    $v = Validator::make(['g-recaptcha-response' => 'robotic'], ['g-recaptcha-response' => [new CaptchaRule]]);

    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('g-recaptcha-response'))->toBeTrue();
});

it('works as the string rule "captcha"', function () {
    Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.9])]);
    expect(Validator::make(['t' => 'good'], ['t' => 'captcha'])->passes())->toBeTrue();
});

it('fails as the string rule "captcha" when verification fails', function () {
    Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => false])]);
    expect(Validator::make(['t' => 'bad'], ['t' => 'captcha'])->fails())->toBeTrue();
});

it('keeps TurnstileRule as a captcha rule for backward compatibility', function () {
    expect(new TurnstileRule)->toBeInstanceOf(CaptchaRule::class);
});
