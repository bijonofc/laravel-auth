<?php

namespace Appsbd\Auth\Facades;

use Appsbd\Auth\Services\TurnstileService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Appsbd\Auth\Support\CaptchaResponse verify(string $token, ?string $ip = null)
 * @method static \Appsbd\Auth\Support\CaptchaResponse verifyOrFail(string $token, ?string $ip = null)
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
