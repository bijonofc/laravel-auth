# Examples

Runnable snippets live in [`examples/`](../examples). They are written for a standard Laravel app namespace (`App\...`) — copy them into your application and adjust.

## [`GoogleAuthController.php`](../examples/GoogleAuthController.php)

A complete controller for the Google redirect flow: `redirect()` sends the user to Google's consent screen (session state is handled automatically), `callback()` completes the exchange, catches `OAuthException` for a friendly failure redirect, and sends the user to their intended page. Pair it with the routes below and the listener for a working login.

## [`TurnstileLoginController.php`](../examples/TurnstileLoginController.php)

A Sanctum-SPA-style JSON login endpoint that validates credentials *and* the Turnstile token in one `validate()` call using the `'turnstile'` rule, then regenerates the session. Returns the user as JSON — exactly what a Vue login store expects.

## [`HandleGoogleLogin.php`](../examples/HandleGoogleLogin.php)

The `GoogleLoginSucceeded` listener: finds or creates the `User` by email (with a random password, since Google owns authentication) and logs them in via the session guard — the same guard Sanctum SPA cookie auth uses. Register it in a service provider or an `Event::listen()` call.

## [`routes.php`](../examples/routes.php)

The `routes/web.php` entries wiring the two controllers: Google redirect + callback routes and the Turnstile-guarded login route. Web (session) middleware context is required for OAuth state and Sanctum cookies.

## [`TurnstileWidget.vue`](../examples/TurnstileWidget.vue)

A reusable Vue 3 `<TurnstileWidget>` component: loads the Cloudflare script once (explicit render mode), renders the widget, emits `verified` with the token, auto-resets on expiry, and exposes `reset()` for after failed submits. Fully documented in [VueIntegration](VueIntegration.md).

## [`RecaptchaV3.vue`](../examples/RecaptchaV3.vue)

A reusable Vue 3 component for the invisible Google reCAPTCHA v3 flow: loads Google's script once, emits `ready`, and exposes `execute(action)` which resolves with a fresh single-use token — call it at submit time and post the token as `g-recaptcha-response`. No widget, no reset dance. Fully documented in [Recaptcha](Recaptcha.md#vue-3-component).
