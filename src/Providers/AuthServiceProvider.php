<?php

namespace Appsbd\Auth\Providers;

use Appsbd\Auth\Contracts\CaptchaProviderInterface;
use Appsbd\Auth\Contracts\OAuthProviderInterface;
use Appsbd\Auth\Services\GoogleOAuthService;
use Appsbd\Auth\Services\TurnstileService;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/appsbd-auth.php', 'appsbd-auth');

        $this->app->singleton(GoogleOAuthService::class, function ($app) {
            return new GoogleOAuthService($app['config']->get('appsbd-auth.google', []));
        });
        $this->app->alias(GoogleOAuthService::class, OAuthProviderInterface::class);

        $this->app->singleton(TurnstileService::class, function ($app) {
            return new TurnstileService($app['config']->get('appsbd-auth.turnstile', []));
        });
        $this->app->alias(TurnstileService::class, CaptchaProviderInterface::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/appsbd-auth.php' => config_path('appsbd-auth.php'),
        ], 'appsbd-auth-config');
    }
}
