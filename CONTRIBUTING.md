# Contributing

Thanks for considering a contribution to `bijon/laravel-auth`.

## Workflow

1. Fork the repository and create a feature branch from `development`.
2. Install dependencies and confirm a green baseline:
   ```bash
   composer install
   composer test
   ```
3. Make your change. **All changes need tests** (Pest, `Http::fake()` for anything external — tests must never hit the network).
4. Update `CHANGELOG.md` under an `[Unreleased]` heading.
5. Open a pull request against `development` describing what and why.

## Rules

- Follow PSR-12 code style; match the existing patterns in `src/`.
- **No breaking changes to `src/Contracts/` within 1.x.** New providers implement the existing interfaces.
- Never log or expose secrets (client secrets, captcha secrets, tokens) in code or tests.
- Use conventional commit prefixes: `feat:`, `fix:`, `docs:`, `ci:`, `test:`, `refactor:`.

## Adding a new provider

New OAuth or captcha providers are additive: a new service implementing `OAuthProviderInterface` or `CaptchaProviderInterface`, a config block, optional facade, tests, and a doc page. See `docs/Publishing.md` for the compatibility policy.
