# Google OAuth

`Bijon\LaravelAuth\Services\GoogleOAuthService` implements `Bijon\LaravelAuth\Contracts\OAuthProviderInterface`. Resolve it via DI, `app(OAuthProviderInterface::class)`, or the `GoogleOAuth` facade.

## API

```php
public function generateAuthorizationUrl(?string $state = null, array $scopes = []): string;
public function redirect(?string $state = null, array $scopes = []): RedirectResponse;
public function callback(?string $code = null, ?string $state = null): array; // ['user' => OAuthUser, 'tokens' => OAuthTokens]
public function getTokensFromCode(string $code): OAuthTokens;
public function getUserFromAccessToken(string $accessToken): OAuthUser;
public function refreshToken(string $refreshToken): OAuthTokens;
public function revokeToken(string $token): bool;
```

DTOs (`Bijon\LaravelAuth\Support`, readonly):

- `OAuthUser` — `id`, `email`, `name`, `avatarUrl`, `raw` (full userinfo array)
- `OAuthTokens` — `accessToken`, `refreshToken`, `expiresIn`, `idToken`, `tokenType`

## State handling (CSRF protection)

**Session mode (default).** Call `generateAuthorizationUrl()` / `redirect()` with no state: a random 40-character state is generated and stored in the session under `laravel-auth.google.state`. When Google redirects back, `callback()` pulls the stored state (removing it — states are single-use) and compares it against the returned state with `hash_equals`. A mismatch throws `OAuthException`.

**Stateless mode (token-only APIs).** Supply `$state` yourself on *both* sides — `generateAuthorizationUrl($myState)` and `callback($code, $myState)`. When no session state exists and you pass state explicitly, session validation is bypassed and your app is responsible for validating the round-trip.

If neither a session state nor an explicit state exists, `callback()` throws `OAuthException`.

## The convenience flow

```php
use Bijon\LaravelAuth\Facades\GoogleOAuth;

// routes/web.php — you write the routes; the package ships none.
Route::get('/auth/google/redirect', fn () => GoogleOAuth::redirect());

Route::get('/auth/google/callback', function () {
    ['user' => $user, 'tokens' => $tokens] = GoogleOAuth::callback();
    // GoogleLoginSucceeded has already been fired here.

    return redirect()->intended('/dashboard');
});
```

`callback()` reads `code` and `state` from the current request when not passed explicitly, validates state, exchanges the code, fetches the user profile, fires events, and returns both DTOs.

## Events

| Event | Payload | Fired when |
|---|---|---|
| `Bijon\LaravelAuth\Events\GoogleLoginSucceeded` | `OAuthUser $user`, `OAuthTokens $tokens` | `callback()` completes successfully |
| `Bijon\LaravelAuth\Events\GoogleLoginFailed` | `string $reason`, `?Throwable $exception` | any `callback()` failure (state, code, HTTP) |

The package never touches your User model — listen to `GoogleLoginSucceeded` and do your own find-or-create + login. See [VueIntegration](VueIntegration.md) and [Examples](Examples.md).

## Errors

| Failure | Exception |
|---|---|
| Missing `client_id` / `client_secret` / `redirect` | `ConfigurationException` naming the key |
| Google HTTP error (4xx/5xx) or connection failure | `OAuthException` with Google's `error_description` when available |
| Token response without `access_token` | `OAuthException` |
| State mismatch / missing state / missing code | `OAuthException` |

Raw HTTP client exceptions never leak; everything is mapped to `OAuthException` (all package exceptions extend `Bijon\LaravelAuth\Exceptions\AuthException`).

## Refresh and revoke

```php
$new = GoogleOAuth::refreshToken($tokens->refreshToken);
// Google omits refresh_token on refresh responses; the service carries your old one forward.

GoogleOAuth::revokeToken($tokens->accessToken); // bool — true when Google confirmed revocation
```
