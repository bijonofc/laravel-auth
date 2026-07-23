# Middleware

The package registers two route middleware aliases:

| Alias | Class | Token input |
|---|---|---|
| `captcha` | `Bijon\LaravelAuth\Http\Middleware\VerifyCaptcha` | the [detected provider](Captcha.md#automatic-provider-detection)'s `input_name` (`cf-turnstile-response` / `g-recaptcha-response`) |
| `turnstile` | `Bijon\LaravelAuth\Http\Middleware\VerifyTurnstile` | `laravel-auth.turnstile.input_name` (default `cf-turnstile-response`) |

Prefer `captcha` — it is provider-agnostic and never needs to change when you switch providers. `turnstile` is kept for backward compatibility as a thin wrapper around the same implementation, pinned to the Turnstile input name.

## Usage

```php
Route::post('/login', [LoginController::class, 'login'])->middleware('captcha');

Route::middleware('captcha')->group(function () {
    Route::post('/register', RegisterController::class);
    Route::post('/contact', ContactController::class);
});
```

The middleware reads the token from the active provider's input name, verifies it with the client IP, and:

- **Success** → passes the request through.
- **Failure, JSON request** (`expectsJson()`) → responds `422`:

  ```json
  {
      "message": "Captcha verification failed. Please try again.",
      "errors": {
          "cf-turnstile-response": ["Captcha verification failed. Please try again."]
      }
  }
  ```

  This is the standard Laravel validation error shape, so SPA error handling that already understands 422 responses works unchanged.

- **Failure, web request** → redirects back with old input (minus the token) and a validation error under the input name key:

  ```blade
  @error('cf-turnstile-response')
      <p class="error">{{ $message }}</p>
  @enderror
  ```

## Changing the input name

```php
// config/laravel-auth.php — under the provider you use
'turnstile' => [
    'input_name' => 'captcha_token',
],
// or
'recaptcha' => [
    'input_name' => 'captcha_token',
],
```

Both the middleware and anything reading the config follow automatically. Keep your frontend widget's response field in sync.

## Caveats

- Captcha tokens are **single-use**. Apply the middleware to state-changing POST routes only — never to GET pages, and don't stack it on a route whose controller *also* validates with the `captcha`/`turnstile` rule (the second check would always fail).
- Choose middleware vs [validation rule](Validation.md): middleware rejects before your controller runs; the rule composes with the rest of your form validation and reports alongside other field errors.
