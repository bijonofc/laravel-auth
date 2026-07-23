<?php

use Bijon\LaravelAuth\Contracts\CaptchaProviderInterface;
use Bijon\LaravelAuth\Exceptions\ConfigurationException;
use Bijon\LaravelAuth\Services\CaptchaManager;
use Bijon\LaravelAuth\Services\RecaptchaV3Service;
use Bijon\LaravelAuth\Services\TurnstileService;
use Bijon\LaravelAuth\Support\CaptchaResponse;
use Illuminate\Support\Facades\Http;

function configureTurnstile(): void
{
    config()->set('laravel-auth.turnstile.site_key', 'tk');
    config()->set('laravel-auth.turnstile.secret', 'ts-secret');
}

function configureRecaptcha(): void
{
    config()->set('laravel-auth.recaptcha.site_key', 'rk');
    config()->set('laravel-auth.recaptcha.secret', 'rc-secret');
}

it('detects turnstile when only turnstile is configured', function () {
    configureTurnstile();

    expect(app(CaptchaManager::class)->detect())->toBe('turnstile')
        ->and(app(CaptchaManager::class)->provider())->toBeInstanceOf(TurnstileService::class);
});

it('detects recaptcha when only recaptcha is configured', function () {
    configureRecaptcha();

    expect(app(CaptchaManager::class)->detect())->toBe('recaptcha')
        ->and(app(CaptchaManager::class)->provider())->toBeInstanceOf(RecaptchaV3Service::class);
});

it('prefers turnstile when both providers are configured', function () {
    configureTurnstile();
    configureRecaptcha();

    expect(app(CaptchaManager::class)->detect())->toBe('turnstile');
});

it('falls back to turnstile when nothing is configured', function () {
    expect(app(CaptchaManager::class)->detect())->toBe('turnstile');
});

it('never silently bypasses: unconfigured verification still throws', function () {
    app(CaptchaManager::class)->verify('token');
})->throws(ConfigurationException::class, 'laravel-auth.turnstile.secret');

it('honors an explicit provider override', function () {
    configureTurnstile();
    configureRecaptcha();
    config()->set('laravel-auth.captcha.provider', 'recaptcha');

    expect(app(CaptchaManager::class)->detect())->toBe('recaptcha');
});

it('throws a meaningful exception for an unknown forced provider', function () {
    config()->set('laravel-auth.captcha.provider', 'hcaptcha');

    app(CaptchaManager::class)->detect();
})->throws(ConfigurationException::class, 'hcaptcha');

it('resolves the interface to the detected provider', function () {
    configureRecaptcha();

    expect(app(CaptchaProviderInterface::class))->toBeInstanceOf(RecaptchaV3Service::class);
});

it('delegates verification to the detected provider', function () {
    configureRecaptcha();
    Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.9])]);

    $result = app(CaptchaManager::class)->verify('token', '1.2.3.4');

    expect($result->success)->toBeTrue()
        ->and($result->provider)->toBe('recaptcha');
});

it('exposes the active provider site key and input name', function () {
    configureRecaptcha();

    $manager = app(CaptchaManager::class);

    expect($manager->siteKey())->toBe('rk')
        ->and($manager->inputName())->toBe('g-recaptcha-response');
});

it('returns null frontend config when no provider is configured', function () {
    expect(app(CaptchaManager::class)->frontendConfig())->toBeNull();
});

it('builds the turnstile frontend config', function () {
    configureTurnstile();

    expect(app(CaptchaManager::class)->frontendConfig())->toBe([
        'provider' => 'turnstile',
        'site_key' => 'tk',
        'input'    => 'cf-turnstile-response',
        'script'   => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
        'params'   => [],
    ]);
});

it('builds the recaptcha frontend config with the render param and default action', function () {
    configureRecaptcha();

    expect(app(CaptchaManager::class)->frontendConfig())->toBe([
        'provider' => 'recaptcha',
        'site_key' => 'rk',
        'input'    => 'g-recaptcha-response',
        'script'   => 'https://www.google.com/recaptcha/api.js?render=rk',
        'params'   => ['action' => 'login'],
    ]);
});

it('uses the configured recaptcha action in frontend params', function () {
    configureRecaptcha();
    config()->set('laravel-auth.recaptcha.action', 'signup');

    expect(app(CaptchaManager::class)->frontendConfig()['params'])->toBe(['action' => 'signup']);
});

it('honors a script_url config override', function () {
    configureTurnstile();
    config()->set('laravel-auth.turnstile.script_url', 'https://example.com/self-hosted.js');

    expect(app(CaptchaManager::class)->frontendConfig()['script'])
        ->toBe('https://example.com/self-hosted.js');
});

it('merges extra data into the frontend config with caller keys winning', function () {
    configureRecaptcha();

    $config = app(CaptchaManager::class)->frontendConfig(['site_key' => 'override', 'page' => 'login']);

    expect($config['site_key'])->toBe('override')
        ->and($config['page'])->toBe('login')
        ->and($config['provider'])->toBe('recaptcha');
});

it('still returns null frontend config when unconfigured, even with extra data', function () {
    expect(app(CaptchaManager::class)->frontendConfig(['x' => 1]))->toBeNull();
});

it('lets apps register additional providers without touching the manager', function () {
    config()->set('laravel-auth.fake.site_key', 'fk');
    config()->set('laravel-auth.fake.secret', 'fs');

    $custom = new class implements CaptchaProviderInterface
    {
        public function verify(string $token, ?string $ip = null): CaptchaResponse
        {
            return new CaptchaResponse(success: true, provider: 'fake');
        }

        public function verifyOrFail(string $token, ?string $ip = null): CaptchaResponse
        {
            return $this->verify($token, $ip);
        }
    };

    $manager = app(CaptchaManager::class)->extend('fake', fn () => $custom);

    expect($manager->detect())->toBe('fake')
        ->and($manager->verify('anything')->provider)->toBe('fake');
});
