# Vue 3 + Sanctum Cookie Auth Integration

End-to-end guide for the package's primary use case: a **Vue 3 SPA** talking to a **Laravel backend with Sanctum cookie (stateful) auth**, using Turnstile-protected login and Google sign-in.

## 1. Prerequisites — Sanctum cookie auth

Your SPA and API must share a top-level domain (e.g. `app.example.com` + `api.example.com`, or same origin). Configure:

```env
SANCTUM_STATEFUL_DOMAINS=app.example.com
SESSION_DOMAIN=.example.com
```

Create a shared axios instance that sends cookies and the XSRF header:

```js
// src/lib/api.js
import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '',
  withCredentials: true,
  withXSRFToken: true,
})

// Call once before the first mutating request (login, register, …).
export async function csrf() {
  await api.get('/sanctum/csrf-cookie')
}

export default api
```

The `/sanctum/csrf-cookie` call sets the `XSRF-TOKEN` cookie; axios echoes it back as the `X-XSRF-TOKEN` header on every subsequent request, which is what keeps you clear of 419 errors.

## 2. The `<TurnstileWidget>` component

A reusable wrapper (full file: [`examples/TurnstileWidget.vue`](../examples/TurnstileWidget.vue)). It loads the Cloudflare script once (explicit render mode), emits the token, auto-resets on expiry, and exposes `reset()`:

```vue
<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  siteKey: { type: String, required: true },
  action: { type: String, default: undefined },
  theme: { type: String, default: 'auto' },
})

const emit = defineEmits(['verified', 'expired', 'errored'])

const container = ref(null)
let widgetId = null

function renderWidget() {
  widgetId = window.turnstile.render(container.value, {
    sitekey: props.siteKey,
    action: props.action,
    theme: props.theme,
    callback: (token) => emit('verified', token),
    'expired-callback': () => {
      emit('expired')
      reset()
    },
    'error-callback': () => emit('errored'),
  })
}

function reset() {
  if (widgetId !== null && window.turnstile) window.turnstile.reset(widgetId)
}

defineExpose({ reset })

onMounted(() => {
  if (window.turnstile) {
    renderWidget()
    return
  }
  const script = document.createElement('script')
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
  script.async = true
  script.onload = renderWidget
  document.head.appendChild(script)
})

onBeforeUnmount(() => {
  if (widgetId !== null && window.turnstile) window.turnstile.remove(widgetId)
})
</script>

<template>
  <div ref="container"></div>
</template>
```

Usage:

```vue
<TurnstileWidget ref="widget" :site-key="siteKey" action="login" @verified="token = $event" />
```

## 3. Login form

Posts through the backend's `'turnstile'` validation (or `turnstile` middleware). **Turnstile tokens are single-use** — on any failed submit, reset the widget and clear the token:

```vue
<!-- src/pages/LoginForm.vue -->
<script setup>
import { ref } from 'vue'
import api, { csrf } from '@/lib/api'
import TurnstileWidget from '@/components/TurnstileWidget.vue'

const siteKey = import.meta.env.VITE_TURNSTILE_SITE_KEY

const email = ref('')
const password = ref('')
const token = ref(null)
const errors = ref({})
const widget = ref(null)

async function submit() {
  errors.value = {}
  try {
    await csrf()
    const { data } = await api.post('/login', {
      email: email.value,
      password: password.value,
      'cf-turnstile-response': token.value,
    })
    // logged in — data.user is available; redirect or update your auth store
    window.location.href = '/dashboard'
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors ?? {}
    }
    token.value = null
    widget.value?.reset() // token was consumed — force a fresh challenge
  }
}
</script>

<template>
  <form @submit.prevent="submit">
    <input v-model="email" type="email" autocomplete="email" placeholder="Email" />
    <p v-if="errors.email">{{ errors.email[0] }}</p>

    <input v-model="password" type="password" autocomplete="current-password" placeholder="Password" />
    <p v-if="errors.password">{{ errors.password[0] }}</p>

    <TurnstileWidget ref="widget" :site-key="siteKey" action="login" @verified="token = $event" />
    <p v-if="errors['cf-turnstile-response']">{{ errors['cf-turnstile-response'][0] }}</p>

    <button type="submit" :disabled="!token">Log in</button>
  </form>
</template>
```

## 4. Google login — redirect flow

Simplest variant: a plain link. The whole OAuth dance happens server-side; session state CSRF protection is automatic.

```vue
<a href="/auth/google/redirect">Continue with Google</a>
```

After the callback the backend has logged the user in (via the `GoogleLoginSucceeded` listener) and redirects to `/dashboard`; the SPA session cookie is already set.

## 5. Google login — popup flow

Keeps the SPA alive during the OAuth dance:

```js
// in your login component
function googleLoginPopup() {
  const popup = window.open('/auth/google/redirect', 'google-login', 'width=520,height=640')

  const listener = async (event) => {
    if (event.origin !== window.origin || event.data?.source !== 'google-auth') return
    window.removeEventListener('message', listener)
    if (event.data.ok) {
      const { data } = await api.get('/api/user') // session cookie is set now
      // update your auth store with data, then route to /dashboard
    }
  }
  window.addEventListener('message', listener)
}
```

Serve a tiny page (Blade or route closure) at the callback's final redirect target for popup mode:

```html
<script>
  if (window.opener) {
    window.opener.postMessage({ source: 'google-auth', ok: true }, window.origin)
    window.close()
  } else {
    window.location.href = '/dashboard'
  }
</script>
```

## 6. Backend

The package ships no routes — copy these examples into your app:

- [`examples/routes.php`](../examples/routes.php) — the three routes
- [`examples/GoogleAuthController.php`](../examples/GoogleAuthController.php) — redirect + callback
- [`examples/TurnstileLoginController.php`](../examples/TurnstileLoginController.php) — JSON login with the `'turnstile'` rule

```php
// routes/web.php
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
Route::post('/login', [TurnstileLoginController::class, 'login'])->name('login');
```

These must live in `routes/web.php` (session middleware): OAuth state and Sanctum SPA auth are both session-cookie based.

## 7. The `GoogleLoginSucceeded` listener

Full file: [`examples/HandleGoogleLogin.php`](../examples/HandleGoogleLogin.php).

```php
use App\Listeners\HandleGoogleLogin;
use Bijon\LaravelAuth\Events\GoogleLoginSucceeded;
use Illuminate\Support\Facades\Event;

// AppServiceProvider::boot()
Event::listen(GoogleLoginSucceeded::class, HandleGoogleLogin::class);
```

```php
public function handle(GoogleLoginSucceeded $event): void
{
    $user = User::query()->firstOrCreate(
        ['email' => $event->user->email],
        ['name' => $event->user->name ?? 'Google User', 'password' => Str::password(32)],
    );

    Auth::login($user, remember: true);
}
```

`Auth::login()` uses the web session guard — the same one Sanctum treats as authenticated for stateful SPA requests. No token handling needed.

## 8. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| **401 after successful login** | SPA host missing from `SANCTUM_STATEFUL_DOMAINS`, or `SESSION_DOMAIN` doesn't cover both hosts | Set both; restart `php artisan config:clear`. Also confirm `withCredentials: true`. |
| **419 on POST /login** | CSRF cookie never fetched, or axios not echoing it | Call `/sanctum/csrf-cookie` first; set `withXSRFToken: true` (axios ≥ 1.6). |
| **`OAuthException`: state mismatch** | Session lost between redirect and callback | Same browser context required (don't start in the SPA and finish in a popup with a different session); check the session driver works and callback URL host matches the app host exactly. |
| **Widget not rendering** | Script blocked, wrong site key, or container unmounted before render | Check the browser console/ad-blockers; verify `VITE_TURNSTILE_SITE_KEY`; keep the widget mounted while visible. |
| **Turnstile always fails on 2nd submit** | Token reuse — they're single-use | Call `widget.reset()` and clear the token after every failed submit (the form above does). |
| **CORS errors** | SPA and API on unrelated domains | Sanctum cookie auth requires a shared top-level domain. Move them under one domain or switch to token auth (not covered by this guide). |
