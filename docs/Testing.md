# Testing Your Integration

The package makes every external call through Laravel's HTTP client, so your app's tests can fake Google and Cloudflare entirely with `Http::fake()` — no network, no test keys.

## Faking Google

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'oauth2.googleapis.com/token' => Http::response([
        'access_token'  => 'fake-access-token',
        'refresh_token' => 'fake-refresh-token',
        'expires_in'    => 3599,
        'id_token'      => 'fake-id-token',
        'token_type'    => 'Bearer',
    ]),
    'openidconnect.googleapis.com/v1/userinfo' => Http::response([
        'sub'     => 'google-user-id',
        'email'   => 'user@example.com',
        'name'    => 'Test User',
        'picture' => 'https://example.com/avatar.png',
    ]),
]);
```

To test error handling, fake a failure:

```php
Http::fake([
    'oauth2.googleapis.com/token' => Http::response([
        'error' => 'invalid_grant', 'error_description' => 'Bad code.',
    ], 400),
]);
// -> the package throws Bijon\LaravelAuth\Exceptions\OAuthException
```

## Faking Turnstile

```php
// Pass:
Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

// Fail with codes:
Http::fake(['challenges.cloudflare.com/*' => Http::response([
    'success' => false, 'error-codes' => ['invalid-input-response'],
])]);
```

With the pass fake in place, requests through the `captcha`/`turnstile` middleware or rule succeed:

```php
Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

$this->postJson('/login', [
    'email' => 'user@example.com',
    'password' => 'secret',
    'cf-turnstile-response' => 'any-token',
])->assertOk();
```

## Faking Google reCAPTCHA v3

```php
// Pass (score above the configured minimum):
Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.9])]);

// Fail on score:
Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.1])]);
// -> failed response with error code 'low-score'

// Fail on action (when laravel-auth.recaptcha.action is set to 'login'):
Http::fake(['www.google.com/recaptcha/*' => Http::response([
    'success' => true, 'score' => 0.9, 'action' => 'signup',
])]);
// -> failed response with error code 'action-mismatch'

// Fail with Google's own codes:
Http::fake(['www.google.com/recaptcha/*' => Http::response([
    'success' => false, 'error-codes' => ['invalid-input-response'],
])]);
```

## Faking events

```php
use Bijon\LaravelAuth\Events\GoogleLoginSucceeded;
use Illuminate\Support\Facades\Event;

Event::fake([GoogleLoginSucceeded::class]);

// ... hit your callback route with the Google fakes above ...

Event::assertDispatched(GoogleLoginSucceeded::class, fn ($e) => $e->user->email === 'user@example.com');
```

Note: `Event::fake()` prevents your real listener from running — use it to test that the package fires the event, and test your listener separately by invoking it directly with a hand-built event.

## Swapping the whole service

For tests where you don't care about HTTP shapes at all, bind a fake implementation of the contract — see [DependencyInjection](DependencyInjection.md).
