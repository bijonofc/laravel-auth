# appsbd/auth

Authentication integrations for Appsbd Laravel products: **Google OAuth2** and **Cloudflare Turnstile**, built for Laravel 12/13 apps — especially Vue 3 SPAs using Sanctum cookie auth.

## Features

- **Google OAuth2** — hand-rolled on the Laravel HTTP client (no Socialite dependency): authorization URL generation, redirect/callback conveniences, code exchange, userinfo, token refresh, and revocation.
- **Cloudflare Turnstile** — server-side verification as a service, a route middleware (`turnstile`), and a validation rule (string `'turnstile'` or `new TurnstileRule`).
- **Services only** — the package ships no routes, controllers, or views. Your app stays in control of its endpoints and its User model.
- **Events, not persistence** — listen to `GoogleLoginSucceeded` and do your own find-or-create + login. The package never touches your database.
- **CSRF-safe OAuth state** — session-backed state is generated and validated automatically, with an explicit override for stateless setups.
- **DI-first** — interfaces bound as container singletons; `GoogleOAuth` and `Turnstile` facades are optional sugar.

## Requirements

- PHP `^8.3`
- Laravel (illuminate components) `^12.0 | ^13.0`

## Installation

```bash
composer require appsbd/auth
```

The service provider, facades, middleware alias, and validation rule are registered via package auto-discovery. Publish the config:

```bash
php artisan vendor:publish --tag=appsbd-auth-config
```

Set your environment variables:

```env
APPSBD_GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
APPSBD_GOOGLE_CLIENT_SECRET=your-client-secret
APPSBD_GOOGLE_REDIRECT_URI=https://your-app.test/auth/google/callback

APPSBD_TURNSTILE_SITE_KEY=0x4AAAAAAA...
APPSBD_TURNSTILE_SECRET=0x4AAAAAAA...
APPSBD_TURNSTILE_TIMEOUT=10
```

## Quick start — Google OAuth

The package returns typed DTOs and fires events; your app writes the routes and owns the User model:

```php
use Appsbd\Auth\Facades\GoogleOAuth;

Route::get('/auth/google/redirect', fn () => GoogleOAuth::redirect());

Route::get('/auth/google/callback', function () {
    GoogleOAuth::callback(); // validates state, exchanges code, fires GoogleLoginSucceeded

    return redirect()->intended('/dashboard');
});
```

Listen to the event to find-or-create your user and log them in:

```php
use Appsbd\Auth\Events\GoogleLoginSucceeded;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

Event::listen(GoogleLoginSucceeded::class, function (GoogleLoginSucceeded $event) {
    $user = User::firstOrCreate(
        ['email' => $event->user->email],
        ['name' => $event->user->name ?? 'Google User', 'password' => Str::password(32)],
    );

    Auth::login($user, remember: true);
});
```

## Quick start — Turnstile

Guard any route with the middleware:

```php
Route::post('/login', LoginController::class)->middleware('turnstile');
```

Or compose it into validation:

```php
$request->validate([
    'cf-turnstile-response' => ['required', 'turnstile'],
]);
```

Or call the service directly:

```php
use Appsbd\Auth\Facades\Turnstile;

$result = Turnstile::verify($token, $request->ip());

if ($result->failed()) {
    // $result->errorCodes
}
```

## Documentation

| Guide | Contents |
|---|---|
| [Installation](docs/Installation.md) | Install, publish config, provider dashboards setup |
| [Configuration](docs/Configuration.md) | Every config key, env vars, error behavior |
| [GoogleOAuth](docs/GoogleOAuth.md) | Full OAuth API, state handling, events, errors |
| [Turnstile](docs/Turnstile.md) | verify/verifyOrFail, response fields, events |
| [Middleware](docs/Middleware.md) | The `turnstile` middleware and failure modes |
| [Validation](docs/Validation.md) | String and object validation rules |
| [Facades](docs/Facades.md) | `GoogleOAuth` and `Turnstile` facades |
| [DependencyInjection](docs/DependencyInjection.md) | Injecting the interfaces, swapping implementations |
| [Testing](docs/Testing.md) | Faking Google/Turnstile in your app's tests |
| [VueIntegration](docs/VueIntegration.md) | **Vue 3 SPA + Sanctum cookie auth, end to end** |
| [Examples](docs/Examples.md) | Runnable controller/listener snippets |
| [Publishing](docs/Publishing.md) | Release process and semver policy |
| [UpgradeGuide](docs/UpgradeGuide.md) | Upgrade notes between versions |

## Testing

```bash
composer test
```

## License

MIT — see [LICENSE](LICENSE).
