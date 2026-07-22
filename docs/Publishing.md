# Publishing & Release Process

## Releasing a version

1. Ensure `development` is green (`composer test`) and CI passes on the full matrix (PHP 8.3/8.4 × Laravel 12/13).
2. Update `CHANGELOG.md`: move `[Unreleased]` entries under the new version heading with today's date.
3. Merge to `main` and tag:
   ```bash
   git tag v1.0.0
   git push origin main --tags
   ```
4. Submit the repository to [Packagist](https://packagist.org/packages/submit) (first release only); subsequent tags are picked up by the GitHub webhook.

## Semver policy

- **Patch (1.0.x)** — bug fixes only.
- **Minor (1.x.0)** — new providers, new methods, new config keys. **`src/Contracts/` is frozen for 1.x**: interfaces never change incompatibly, config keys are only ever added, and deprecations get at least one minor release of runtime warning before removal in 2.0.
- **Major (2.0.0)** — anything breaking.

## Adding future providers without breaking changes

The v1 architecture reserves room for Microsoft, GitHub, Facebook, Apple, LinkedIn, reCAPTCHA v2/v3, hCaptcha, OTP, and Passkeys:

1. New service in `src/Services/` implementing the existing `OAuthProviderInterface` or `CaptchaProviderInterface`.
2. New config block in `config/appsbd-auth.php` (additive).
3. Optional facade + auto-discovery alias.
4. New events following the `<Provider>LoginSucceeded` / `<Provider>Verified` naming.
5. Tests with `Http::fake()` and a doc page.

Consumers who type-hint the contracts can switch providers via a container binding without touching call sites.
