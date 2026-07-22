<?php

namespace Appsbd\Auth\Tests;

use Appsbd\Auth\Providers\AuthServiceProvider;
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
            'GoogleOAuth' => \Appsbd\Auth\Facades\GoogleOAuth::class,
            'Turnstile'   => \Appsbd\Auth\Facades\Turnstile::class,
        ];
    }
}
