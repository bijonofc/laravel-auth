# Google reCAPTCHA v3

`Bijon\LaravelAuth\Services\RecaptchaV3Service` implements `Bijon\LaravelAuth\Contracts\CaptchaProviderInterface`. It is one provider of the shared [captcha system](Captcha.md) — resolve it via DI, the [`Captcha` facade](Facades.md) (when detected as active), or `app(RecaptchaV3Service::class)` explicitly.

reCAPTCHA v3 is invisible: instead of a challenge it returns a **score** (1.0 = likely human, 0.0 = likely bot) and the **action** name your frontend tagged the request with. Verification passes only when Google reports success *and* the score/action checks pass.

## Configuration

```env
LA_RECAPTCHA_SITE_KEY=6Lc...
LA_RECAPTCHA_SECRET=6Lc...
LA_RECAPTCHA_SCORE=0.5      # optional, default 0.5
LA_RECAPTCHA_ACTION=login   # optional, default: not enforced
LA_RECAPTCHA_TIMEOUT=10     # optional, default 10
```

Setting the site key + secret is all it takes — [automatic detection](Captcha.md#automatic-provider-detection) activates the provider (unless Turnstile is also configured, which takes priority; force with `LA_CAPTCHA_PROVIDER=recaptcha`). Keys come from the [reCAPTCHA admin console](https://www.google.com/recaptcha/admin) — create a **v3** site.

## API

```php
public function verify(string $token, ?string $ip = null): CaptchaResponse;
public function verifyOrFail(string $token, ?string $ip = null): CaptchaResponse; // throws CaptchaException on failure
```

`verify()` POSTs the token (and optionally the client IP) to Google's siteverify endpoint with the configured timeout, then applies the score and action checks.

## `CaptchaResponse` fields

Same DTO as every provider — see the full table in [Turnstile](Turnstile.md#captcharesponse-fields). reCAPTCHA-specific values:

| Field | Value |
|---|---|
| `provider` | `recaptcha` |
| `score` | Google's score (`0.0`–`1.0`), or `null` when Google sent none |
| `action` | the action name the token was generated for |
| `cdata` | always `null` (Turnstile-only) |
| `errorCodes` | Google's codes (e.g. `invalid-input-response`, `timeout-or-duplicate`), plus the package's `low-score`, `action-mismatch`, `internal-error`, `network-error` |

## Failure semantics

Identical to [Turnstile's](Turnstile.md#failure-semantics) — invalid tokens return a failed response, HTTP 5xx returns `['internal-error']`, transport failures return `['network-error']` and are `report()`ed, verification always fails closed — with two additional checks:

- **Low score** → a Google-successful response whose score is below `min_score` fails with `['low-score']`. A missing score also fails (`score` stays `null`) — the check never silently passes.
- **Action mismatch** → when `laravel-auth.recaptcha.action` is set and the token's action differs, verification fails with `['action-mismatch']`. Leave the config `null` to skip the check.
- **Missing secret** → `ConfigurationException` naming `laravel-auth.recaptcha.secret`.

`verifyOrFail()` throws `Bijon\LaravelAuth\Exceptions\CaptchaException`; the response is available as `$e->response`.

## Events

The provider-agnostic `Bijon\LaravelAuth\Events\CaptchaVerified` / `CaptchaFailed` fire with the `CaptchaResponse` payload — see [Captcha events](Captcha.md#events-and-exceptions).

## Middleware and validation

Nothing reCAPTCHA-specific — the same [`captcha` middleware](Middleware.md) and [`captcha` rule](Validation.md) serve every provider:

```php
Route::post('/login', LoginController::class)->middleware('captcha');

$request->validate([
    'g-recaptcha-response' => ['required', 'captcha'],
]);
```

The middleware reads the token from `laravel-auth.recaptcha.input_name` (default `g-recaptcha-response`, the name Google's own snippets use).

## Frontend integration

Load the script with your site key, execute on submit, and post the token:

```html
<form method="POST" action="/login" id="login-form">
    <!-- your fields -->
    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
    <button type="submit">Log in</button>
</form>

<script src="https://www.google.com/recaptcha/api.js?render={{ config('laravel-auth.recaptcha.site_key') }}"></script>
<script>
    document.getElementById('login-form').addEventListener('submit', function (e) {
        e.preventDefault();
        grecaptcha.ready(() => {
            grecaptcha.execute('{{ config('laravel-auth.recaptcha.site_key') }}', { action: 'login' })
                .then((token) => {
                    document.getElementById('g-recaptcha-response').value = token;
                    e.target.submit();
                });
        });
    });
</script>
```

The `action` you pass to `grecaptcha.execute()` is what `LA_RECAPTCHA_ACTION` is compared against. For SPAs, fetch the site key and input name from the backend via [`Captcha::siteKey()` / `Captcha::inputName()`](Captcha.md#the-manager) so the frontend stays provider-agnostic.

Remember: reCAPTCHA tokens are **single-use** and expire after ~2 minutes — generate the token at submit time, not at page load.

## Vue 3 component

A reusable wrapper (full file: [`examples/RecaptchaV3.vue`](../examples/RecaptchaV3.vue)). Unlike the [`<TurnstileWidget>`](VueIntegration.md) there is nothing to render — v3 is invisible. The component loads Google's script once and exposes `execute(action)`, which resolves with a fresh single-use token:

```vue
<script setup>
import { onMounted, ref } from 'vue'

const props = defineProps({
  siteKey: { type: String, required: true },
  action: { type: String, default: 'submit' },
})

const emit = defineEmits(['ready', 'errored'])

const ready = ref(false)

function onScriptLoaded() {
  window.grecaptcha.ready(() => {
    ready.value = true
    emit('ready')
  })
}

// Fetch a fresh single-use token. Call this at submit time, not at page
// load — v3 tokens expire after ~2 minutes.
function execute(action = props.action) {
  if (!window.grecaptcha) {
    return Promise.reject(new Error('reCAPTCHA script not loaded yet'))
  }
  return window.grecaptcha.execute(props.siteKey, { action })
}

defineExpose({ execute, ready })

onMounted(() => {
  if (window.grecaptcha) {
    onScriptLoaded()
    return
  }
  const existing = document.querySelector('script[data-recaptcha-v3]')
  if (existing) {
    existing.addEventListener('load', onScriptLoaded)
    return
  }
  const script = document.createElement('script')
  script.src = `https://www.google.com/recaptcha/api.js?render=${props.siteKey}`
  script.async = true
  script.dataset.recaptchaV3 = ''
  script.onload = onScriptLoaded
  script.onerror = () => emit('errored')
  document.head.appendChild(script)
})
</script>

<template>
  <slot :ready="ready" :execute="execute"></slot>
</template>
```

### Login form usage

The same Sanctum-cookie login form as the [Turnstile Vue guide](VueIntegration.md#3-login-form), with the widget swapped for `execute()` at submit time — note there is **no reset dance**: a failed submit just executes a fresh token on retry:

```vue
<!-- src/pages/LoginForm.vue -->
<script setup>
import { ref } from 'vue'
import api, { csrf } from '@/lib/api'
import RecaptchaV3 from '@/components/RecaptchaV3.vue'

const siteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY

const email = ref('')
const password = ref('')
const errors = ref({})
const recaptcha = ref(null)

async function submit() {
  errors.value = {}
  try {
    await csrf()
    const token = await recaptcha.value.execute('login') // fresh token per attempt
    const { data } = await api.post('/login', {
      email: email.value,
      password: password.value,
      'g-recaptcha-response': token,
    })
    // logged in — data.user is available; redirect or update your auth store
    window.location.href = '/dashboard'
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors ?? {}
    }
  }
}
</script>

<template>
  <form @submit.prevent="submit">
    <input v-model="email" type="email" autocomplete="email" placeholder="Email" />
    <p v-if="errors.email">{{ errors.email[0] }}</p>

    <input v-model="password" type="password" autocomplete="current-password" placeholder="Password" />
    <p v-if="errors.password">{{ errors.password[0] }}</p>

    <RecaptchaV3 ref="recaptcha" :site-key="siteKey" action="login" />
    <p v-if="errors['g-recaptcha-response']">{{ errors['g-recaptcha-response'][0] }}</p>

    <button type="submit">Log in</button>
  </form>
</template>
```

The `action` passed to `execute()` (here `login`) must match `LA_RECAPTCHA_ACTION` if you enforce it. Google requires its **badge** to stay visible; if you hide it with CSS you must include Google's [attribution text](https://developers.google.com/recaptcha/docs/faq#id-like-to-hide-the-recaptcha-badge.-what-is-allowed) in the form instead.

## Testing

```php
// Pass:
Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.9])]);

// Fail on score:
Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.1])]);
```

See [Testing](Testing.md) for complete recipes.
