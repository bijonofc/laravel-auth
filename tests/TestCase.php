<?php

namespace Appsbd\Auth\Tests;

use Appsbd\Auth\Providers\AuthServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [AuthServiceProvider::class];
    }
}
