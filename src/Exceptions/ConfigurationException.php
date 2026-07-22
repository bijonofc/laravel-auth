<?php

namespace Bijon\LaravelAuth\Exceptions;

class ConfigurationException extends AuthException
{
    public static function missing(string $key): self
    {
        return new self("Missing or empty required config value [{$key}]. Publish the config with `php artisan vendor:publish --tag=laravel-auth-config` and set the corresponding environment variable.");
    }
}
