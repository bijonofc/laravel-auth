# Facades

Two facades are registered by auto-discovery. They are optional sugar over the container singletons — everything they do is available via [dependency injection](DependencyInjection.md).

## `Appsbd\Auth\Facades\GoogleOAuth`

| Method | Returns |
|---|---|
| `generateAuthorizationUrl(?string $state = null, array $scopes = [])` | `string` |
| `redirect(?string $state = null, array $scopes = [])` | `RedirectResponse` |
| `callback(?string $code = null, ?string $state = null)` | `array{user: OAuthUser, tokens: OAuthTokens}` |
| `getTokensFromCode(string $code)` | `OAuthTokens` |
| `getUserFromAccessToken(string $accessToken)` | `OAuthUser` |
| `refreshToken(string $refreshToken)` | `OAuthTokens` |
| `revokeToken(string $token)` | `bool` |

## `Appsbd\Auth\Facades\Turnstile`

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
