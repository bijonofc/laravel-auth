<?php

namespace Bijon\LaravelAuth\Providers;

use Bijon\LaravelAuth\Contracts\CaptchaProviderInterface;
use Bijon\LaravelAuth\Contracts\OAuthProviderInterface;
use Bijon\LaravelAuth\Http\Middleware\VerifyTurnstile;
use Bijon\LaravelAuth\Services\GoogleOAuthService;
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
        $this->app->alias(TurnstileService::class, CaptchaProviderInterface::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/laravel-auth.php' => config_path('laravel-auth.php'),
        ], 'laravel-auth-config');

        $this->app['router']->aliasMiddleware('turnstile', VerifyTurnstile::class);

        Validator::extend('turnstile', function ($attribute, $value) {
            return app(CaptchaProviderInterface::class)
                ->verify((string) $value, request()->ip())
                ->success;
        }, 'The :attribute field failed captcha verification.');
    }
}
