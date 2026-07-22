# Upgrade Guide

## 1.0.0

Initial release — nothing to upgrade from.

## Compatibility policy

When future versions ship, this page will list every step needed to upgrade. The standing guarantees within the 1.x series:

- **Contracts are frozen.** `OAuthProviderInterface` and `CaptchaProviderInterface` will not change incompatibly.
- **Config keys are only added, never renamed or removed.** Your published `config/appsbd-auth.php` keeps working; re-publish with `--force` only if you want new commented defaults.
- **Events keep their constructor signatures.** New data arrives as new public properties.
- **Deprecations warn first.** Anything slated for removal in 2.0 triggers a deprecation notice for at least one minor release beforehand.
