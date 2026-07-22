# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 1.x | ✅ |

## Reporting a vulnerability

Please report security vulnerabilities **privately** to `support@appsbd.com`. Do **not** open a public GitHub issue for security reports.

You can expect an acknowledgment within 7 days. Please include a proof of concept and the affected version range if you can.

## Scope notes

This package handles sensitive material: OAuth client secrets, access/refresh tokens, and the Turnstile secret key.

- Secrets are read from config/environment only; the package never logs them and never includes them in exception messages.
- OAuth state is validated with `hash_equals` to prevent timing attacks; sessions-backed state defends against login CSRF.
- `TurnstileService::verify()` fails closed (a network error is a failed verification, never a pass).
