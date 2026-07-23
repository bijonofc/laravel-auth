<?php

namespace Bijon\LaravelAuth\Facades;

use Bijon\LaravelAuth\Services\CaptchaManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Bijon\LaravelAuth\Support\CaptchaResponse verify(string $token, ?string $ip = null)
 * @method static \Bijon\LaravelAuth\Support\CaptchaResponse verifyOrFail(string $token, ?string $ip = null)
 * @method static \Bijon\LaravelAuth\Contracts\CaptchaProviderInterface provider(?string $name = null)
 * @method static \Bijon\LaravelAuth\Services\CaptchaManager extend(string $name, string|\Closure $provider)
 * @method static string detect()
 * @method static bool isConfigured(string $name)
 * @method static list<string> providers()
 * @method static ?string siteKey()
 * @method static string inputName()
 * @method static ?array frontendConfig(array $extra = [])
 *
 * @see CaptchaManager
 */
class Captcha extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CaptchaManager::class;
    }
}
