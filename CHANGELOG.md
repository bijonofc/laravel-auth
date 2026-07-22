# Changelog

All notable changes to `appsbd/auth` are documented here. Follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and semver.

## [1.0.0] - 2026-07-22

### Added
- Google OAuth2 service: authorization URL, redirect/callback conveniences, code exchange, userinfo, refresh, revoke, session-backed CSRF state with stateless override.
- Cloudflare Turnstile service: `verify` / `verifyOrFail`, network-safe failure mode.
- `turnstile` middleware (JSON 422 / redirect-back) and validation rule (string + object).
- Events: `GoogleLoginSucceeded`, `GoogleLoginFailed`, `TurnstileVerified`, `TurnstileFailed`.
- Facades `GoogleOAuth` and `Turnstile`; interface singletons for DI.
- Publishable config, full documentation set including Vue 3 + Sanctum integration guide.
