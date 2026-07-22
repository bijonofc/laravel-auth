# Changelog

All notable changes to `bijon/laravel-auth` are documented here. Follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and semver.

## [1.0.1] - 2026-07-22

### Added
- All outbound HTTPS requests (Cloudflare siteverify, Google OAuth endpoints) now send an explicit CA bundle via `composer/ca-bundle`, so TLS verification works on hosts without a configured certificate store (e.g. Windows WAMP/XAMPP) — no server configuration needed.

### Changed
- **Package renamed** from `appsbd/auth` to `bijon/laravel-auth` for publication on Packagist, with a full rebrand. Existing installs must update: the `require` entry in `composer.json`, PHP imports (`Appsbd\Auth\*` → `Bijon\LaravelAuth\*`), the published config file (`config/appsbd-auth.php` → `config/laravel-auth.php`, re-publish with `--tag=laravel-auth-config`), any `config('appsbd-auth.*')` calls (→ `config('laravel-auth.*')`), and `.env` keys (`APPSBD_*` → `LA_*`).
- `TurnstileService::verify()` now returns `['network-error']` (previously `['internal-error']`) when Cloudflare cannot be reached, and passes the underlying exception to `report()` so the real cause appears in the host app's log. HTTP error responses from Cloudflare still return `['internal-error']`.

## [1.0.0] - 2026-07-22

### Added
- Google OAuth2 service: authorization URL, redirect/callback conveniences, code exchange, userinfo, refresh, revoke, session-backed CSRF state with stateless override.
- Cloudflare Turnstile service: `verify` / `verifyOrFail`, network-safe failure mode.
- `turnstile` middleware (JSON 422 / redirect-back) and validation rule (string + object).
- Events: `GoogleLoginSucceeded`, `GoogleLoginFailed`, `TurnstileVerified`, `TurnstileFailed`.
- Facades `GoogleOAuth` and `Turnstile`; interface singletons for DI.
- Publishable config, full documentation set including Vue 3 + Sanctum integration guide.
