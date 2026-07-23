# Facades

Three facades are registered by auto-discovery. They are optional sugar over the container singletons — everything they do is available via [dependency injection](DependencyInjection.md).

## `Bijon\LaravelAuth\Facades\GoogleOAuth`

| Method | Returns |
|---|---|
| `generateAuthorizationUrl(?string $state = null, array $scopes = [])` | `string` |
| `redirect(?string $state = null, array $scopes = [])` | `RedirectResponse` |
| `callback(?string $code = null, ?string $state = null)` | `array{user: OAuthUser, tokens: OAuthTokens}` |
| `getTokensFromCode(string $code)` | `OAuthTokens` |
| `getUserFromAccessToken(string $accessToken)` | `OAuthUser` |
| `refreshToken(string $refreshToken)` | `OAuthTokens` |
| `revokeToken(string $token)` | `bool` |

## `Bijon\LaravelAuth\Facades\Captcha`

Fronts the [`CaptchaManager`](Captcha.md#the-manager) — provider-agnostic, always acting on the [auto-detected provider](Captcha.md#automatic-provider-detection):

| Method | Returns |
|---|---|
| `verify(string $token, ?string $ip = null)` | `CaptchaResponse` |
| `verifyOrFail(string $token, ?string $ip = null)` | `CaptchaResponse` (throws `CaptchaException` on failure) |
| `detect()` | `string` — the active provider name |
| `provider(?string $name = null)` | `CaptchaProviderInterface` |
| `isConfigured(string $name)` | `bool` |
| `providers()` | `list<string>` |
| `siteKey()` | `?string` — the active provider's public site key |
| `inputName()` | `string` — the request input the frontend submits the token under |
| `extend(string $name, string\|Closure $provider)` | `CaptchaManager` — [register a custom provider](Captcha.md#adding-your-own-provider) |

## `Bijon\LaravelAuth\Facades\Turnstile`

Pinned to the Turnstile provider regardless of detection:

| Method | Returns |
|---|---|
| `verify(string $token, ?string $ip = null)` | `CaptchaResponse` |
| `verifyOrFail(string $token, ?string $ip = null)` | `CaptchaResponse` (throws `TurnstileException` on failure) |

## Testability

The services make all external calls through Laravel's HTTP client, so `Http::fake()` works whether you call through facades or injected instances:

```php
Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

Turnstile::verify('token')->success; // true, no network call
```

See [Testing](Testing.md) for complete fake recipes.
