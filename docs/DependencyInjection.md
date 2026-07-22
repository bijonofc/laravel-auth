# Dependency Injection

The service provider binds both services as **singletons** and aliases the contracts to them:

| Contract | Implementation |
|---|---|
| `Bijon\LaravelAuth\Contracts\OAuthProviderInterface` | `Bijon\LaravelAuth\Services\GoogleOAuthService` |
| `Bijon\LaravelAuth\Contracts\CaptchaProviderInterface` | `Bijon\LaravelAuth\Services\TurnstileService` |

Type-hint the contract anywhere the container resolves:

```php
use Bijon\LaravelAuth\Contracts\OAuthProviderInterface;
use Bijon\LaravelAuth\Contracts\CaptchaProviderInterface;

class GoogleAuthController extends Controller
{
    public function __construct(private OAuthProviderInterface $google)
    {
    }

    public function redirect()
    {
        return redirect($this->google->generateAuthorizationUrl());
    }
}

class LoginController extends Controller
{
    public function __construct(private CaptchaProviderInterface $captcha)
    {
    }
}
```

## Lifecycle

Services are constructed lazily on first resolution, reading `config('laravel-auth.google')` / `config('laravel-auth.turnstile')` at that moment. If a test mutates config, do it **before** the first resolution in that test.

## Swapping implementations in tests

```php
use Bijon\LaravelAuth\Contracts\CaptchaProviderInterface;
use Bijon\LaravelAuth\Support\CaptchaResponse;

$fake = new class implements CaptchaProviderInterface {
    public function verify(string $token, ?string $ip = null): CaptchaResponse
    {
        return new CaptchaResponse(success: true);
    }

    public function verifyOrFail(string $token, ?string $ip = null): CaptchaResponse
    {
        return new CaptchaResponse(success: true);
    }
};

$this->app->instance(CaptchaProviderInterface::class, $fake);
```

Because the middleware and validation rule resolve the contract from the container, the fake is used everywhere. Usually `Http::fake()` is simpler — see [Testing](Testing.md).
