# bijon/laravel-auth

Authentication integrations for Laravel: **Google OAuth2** and **CAPTCHA verification** (Cloudflare Turnstile, Google reCAPTCHA v3), built for Laravel 12/13 apps — especially Vue 3 SPAs using Sanctum cookie auth.

## Features

- **Google OAuth2** — hand-rolled on the Laravel HTTP client (no Socialite dependency): authorization URL generation, redirect/callback conveniences, code exchange, userinfo, token refresh, and revocation.
- **Provider-based CAPTCHA** — one system, multiple providers: **Cloudflare Turnstile** and **Google reCAPTCHA v3** (score + action checks) built in, custom providers via `Captcha::extend()`. The active provider is **auto-detected** from your env vars.
- **Provider-agnostic API** — a route middleware (`captcha`), a validation rule (string `'captcha'` or `new CaptchaRule`), and a unified `CaptchaResponse` DTO, identical whichever provider is configured. The `turnstile` middleware/rule aliases keep working.
- **Services only** — the package ships no routes, controllers, or views. Your app stays in control of its endpoints and its User model.
- **Events, not persistence** — listen to `GoogleLoginSucceeded` and do your own find-or-create + login. The package never touches your database.
- **CSRF-safe OAuth state** — session-backed state is generated and validated automatically, with an explicit override for stateless setups.
- **DI-first** — interfaces bound as container singletons; `GoogleOAuth` and `Turnstile` facades are optional sugar.

## Requirements

- PHP `^8.3`
- Laravel (illuminate components) `^12.0 | ^13.0`

## Installation

```bash
composer require bijon/laravel-auth
```

The service provider, facades, middleware alias, and validation rule are registered via package auto-discovery. Publish the config:

```bash
php artisan vendor:publish --tag=laravel-auth-config
```

Set your environment variables:

```env
LA_GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
LA_GOOGLE_CLIENT_SECRET=your-client-secret
LA_GOOGLE_REDIRECT_URI=https://your-app.test/auth/google/callback

# Captcha — configure ONE provider; the package auto-detects it.
# Cloudflare Turnstile:
LA_TURNSTILE_SITE_KEY=0x4AAAAAAA...
LA_TURNSTILE_SECRET=0x4AAAAAAA...

# ... or Google reCAPTCHA v3:
LA_RECAPTCHA_SITE_KEY=6Lc...
LA_RECAPTCHA_SECRET=6Lc...
LA_RECAPTCHA_SCORE=0.5
LA_RECAPTCHA_ACTION=login
```

## Quick start — Google OAuth

The package returns typed DTOs and fires events; your app writes the routes and owns the User model:

```php
use Bijon\LaravelAuth\Facades\GoogleOAuth;

Route::get('/auth/google/redirect', fn () => GoogleOAuth::redirect());

Route::get('/auth/google/callback', function () {
    GoogleOAuth::callback(); // validates state, exchanges code, fires GoogleLoginSucceeded

    return redirect()->intended('/dashboard');
});
```

Listen to the event to find-or-create your user and log them in:

```php
use Bijon\LaravelAuth\Events\GoogleLoginSucceeded;
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

## Quick start — Captcha

Set the env vars for either Turnstile **or** reCAPTCHA v3 — the package detects which one you configured. Then guard any route with the middleware:

```php
Route::post('/login', LoginController::class)->middleware('captcha');
```

Or compose it into validation:

```php
$request->validate([
    'cf-turnstile-response' => ['required', 'captcha'], // g-recaptcha-response for reCAPTCHA
]);
```

Or call the service directly — the `Captcha` facade always talks to the detected provider:

```php
use Bijon\LaravelAuth\Facades\Captcha;

$result = Captcha::verify($token, $request->ip());

if ($result->failed()) {
    // $result->errorCodes, $result->score (reCAPTCHA), $result->provider
}
```

`Captcha::detect()`, `Captcha::siteKey()`, and `Captcha::inputName()` give your frontend everything it needs without hardcoding a provider. Existing Turnstile integrations (`turnstile` middleware/rule, `Turnstile` facade) keep working unchanged.

### Frontend bootstrap in one line

Instead of wiring those pieces up separately, drop the whole frontend config into your blade layout with the `@captchaConfig` directive:

```blade
<script>
    window.app_settings = { captcha: @captchaConfig };
</script>
```

Pass an array to merge extra data into the JSON (your keys win over the defaults):

```blade
window.app_settings = { captcha: @captchaConfig(['page' => 'login', 'theme' => 'dark']) };
```

The directive is equivalent to `@json(\Bijon\LaravelAuth\Facades\Captcha::frontendConfig())`, which you can still call directly — `frontendConfig(array $extra = [])` accepts the same merge argument. It returns `null` (the directive prints literal `null`, still valid JSON) when no captcha provider is configured — even when `$extra` is non-empty, since no provider means there is no widget to render. Otherwise:

```json
{
    "provider": "recaptcha",
    "site_key": "6Lc...",
    "input": "g-recaptcha-response",
    "script": "https://www.google.com/recaptcha/api.js?render=6Lc...",
    "params": { "action": "login" }
}
```

Your SPA can then render the active widget without knowing which provider is behind it:

```js
const captcha = window.app_settings.captcha;

if (captcha) {
    // Load the provider script dynamically.
    const script = document.createElement('script');
    script.src = captcha.script;
    script.async = true;
    document.head.appendChild(script);
}

async function captchaToken() {
    if (!captcha) return {};

    if (captcha.provider === 'recaptcha') {
        const token = await grecaptcha.execute(captcha.site_key, { action: captcha.params.action });
        return { [captcha.input]: token };
    }

    // turnstile: read the token the widget wrote into the form
    return { [captcha.input]: document.querySelector(`[name="${captcha.input}"]`)?.value };
}

await axios.post('/login', { email, password, ...(await captchaToken()) });
```

`params` carries provider-specific extras (for reCAPTCHA v3, the `action` — `laravel-auth.recaptcha.action`, defaulting to `login`). Each provider's script URL can be overridden with a `script_url` config key.

## Documentation

| Guide | Contents |
|---|---|
| [Installation](docs/Installation.md) | Install, publish config, provider dashboards setup |
| [Configuration](docs/Configuration.md) | Every config key, env vars, error behavior |
| [GoogleOAuth](docs/GoogleOAuth.md) | Full OAuth API, state handling, events, errors |
| [Captcha](docs/Captcha.md) | Provider system, auto-detection, manager, custom providers |
| [Turnstile](docs/Turnstile.md) | verify/verifyOrFail, response fields, events |
| [Recaptcha](docs/Recaptcha.md) | reCAPTCHA v3: score/action checks, frontend token flow |
| [Middleware](docs/Middleware.md) | The `captcha`/`turnstile` middleware and failure modes |
| [Validation](docs/Validation.md) | String and object validation rules |
| [Facades](docs/Facades.md) | `GoogleOAuth` and `Turnstile` facades |
| [DependencyInjection](docs/DependencyInjection.md) | Injecting the interfaces, swapping implementations |
| [Testing](docs/Testing.md) | Faking Google, Turnstile, and reCAPTCHA in your app's tests |
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
