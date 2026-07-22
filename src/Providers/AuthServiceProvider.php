<?php

namespace Appsbd\Auth\Providers;

use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/appsbd-auth.php', 'appsbd-auth');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/appsbd-auth.php' => config_path('appsbd-auth.php'),
        ], 'appsbd-auth-config');
    }
}
