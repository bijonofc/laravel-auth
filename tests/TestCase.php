<?php

namespace Bijon\LaravelAuth\Tests;

use Bijon\LaravelAuth\Providers\AuthServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function getPackageProviders($app): array
    {
        return [AuthServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'GoogleOAuth' => \Bijon\LaravelAuth\Facades\GoogleOAuth::class,
            'Turnstile'   => \Bijon\LaravelAuth\Facades\Turnstile::class,
        ];
    }
}
