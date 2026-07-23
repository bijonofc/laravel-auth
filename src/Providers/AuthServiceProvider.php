<?php

namespace Bijon\LaravelAuth\Providers;

use Bijon\LaravelAuth\Contracts\CaptchaProviderInterface;
use Bijon\LaravelAuth\Contracts\OAuthProviderInterface;
use Bijon\LaravelAuth\Http\Middleware\VerifyCaptcha;
use Bijon\LaravelAuth\Http\Middleware\VerifyTurnstile;
use Bijon\LaravelAuth\Services\CaptchaManager;
use Bijon\LaravelAuth\Services\GoogleOAuthService;
use Bijon\LaravelAuth\Services\RecaptchaV3Service;
use Bijon\LaravelAuth\Services\TurnstileService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/laravel-auth.php', 'laravel-auth');

        $this->app->singleton(GoogleOAuthService::class, function ($app) {
            return new GoogleOAuthService($app['config']->get('laravel-auth.google', []));
        });
        $this->app->alias(GoogleOAuthService::class, OAuthProviderInterface::class);

        $this->app->singleton(TurnstileService::class, function ($app) {
            return new TurnstileService($app['config']->get('laravel-auth.turnstile', []));
        });

        $this->app->singleton(RecaptchaV3Service::class, function ($app) {
            return new RecaptchaV3Service($app['config']->get('laravel-auth.recaptcha', []));
        });

        $this->app->singleton(CaptchaManager::class, function ($app) {
            return (new CaptchaManager($app))
                ->extend('turnstile', TurnstileService::class)
                ->extend('recaptcha', RecaptchaV3Service::class);
        });

        // The interface resolves to the auto-detected provider on every resolution,
        // so config changes (and custom rebindings) always take effect.
        $this->app->bind(CaptchaProviderInterface::class, function ($app) {
            return $app->make(CaptchaManager::class)->provider();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/laravel-auth.php' => config_path('laravel-auth.php'),
        ], 'laravel-auth-config');

        $this->app['router']->aliasMiddleware('captcha', VerifyCaptcha::class);
        $this->app['router']->aliasMiddleware('turnstile', VerifyTurnstile::class);

        $verifyCaptcha = function ($attribute, $value) {
            return app(CaptchaProviderInterface::class)
                ->verify((string) $value, request()->ip())
                ->success;
        };

        Validator::extend('captcha', $verifyCaptcha, 'The :attribute field failed captcha verification.');
        Validator::extend('turnstile', $verifyCaptcha, 'The :attribute field failed captcha verification.');
    }
}
