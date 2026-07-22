<?php

namespace Bijon\LaravelAuth\Facades;

use Bijon\LaravelAuth\Services\TurnstileService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Bijon\LaravelAuth\Support\CaptchaResponse verify(string $token, ?string $ip = null)
 * @method static \Bijon\LaravelAuth\Support\CaptchaResponse verifyOrFail(string $token, ?string $ip = null)
 *
 * @see TurnstileService
 */
class Turnstile extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TurnstileService::class;
    }
}
