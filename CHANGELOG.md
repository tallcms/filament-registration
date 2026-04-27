# Changelog

All notable changes to `tallcms/filament-registration` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.2] - 2026-04-27

### Fixed

- **Empty DB values no longer clobber env defaults during config merge.** `mergeDbSettingsIntoConfig()` was overriding runtime config for any key present in `tallcms_registration_settings`, even when the stored value was an empty string. This silently broke env-driven setups where the user pressed Save in the admin form before filling all fields — a blank site_key in the DB would clobber a valid `FILAMENT_REGISTRATION_CAPTCHA_SITE_KEY` env var and the captcha manager would fall back to `NullCaptchaProvider`. The merge now skips empty-string and null values, so env wins when the DB row is unfilled. Booleans (including `false`), numbers (including `0`), and non-empty strings all still override env as before.

## [1.1.1] - 2026-04-27

### Fixed

- **Captcha field no longer shows "Cf turnstile response" / "G recaptcha response" as its label** above the widget. Filament was deriving the label from the underlying token field name; the field now sets an accessible "Verification" label and hides it visually since the captcha widget speaks for itself. Validation messages and screen readers still get a sensible identity.

## [1.1.0] - 2026-04-27

### Changed

- **`spatie/laravel-permission` is now an optional dependency** (moved from `require` to `suggest`). The Register page checks `class_exists(Spatie\Permission\Models\Role::class)` before attempting role assignment, so hosts that don't use Spatie roles get a no-op instead of a fatal class-not-found error. Spatie users continue to work without changes.
- **Removed `HasPageShield` trait from the default `RegistrationSettings` page**. Filament Shield is no longer a required dependency. By default the settings page is accessible to any user who can reach the panel; hosts wanting stricter gating subclass the page and either add `HasPageShield` (Shield users), override `canAccess()` (any custom check), or rely on panel `authMiddleware`.

### Migration from 1.0.0

If you installed v1.0.0 and ran `shield:generate --page=RegistrationSettings`, the resulting `View:RegistrationSettings` permission row is now orphan but harmless — the page no longer reads it. To restore Shield-based gating in v1.1.0, subclass the page in your app and add the `HasPageShield` trait, then point the panel at your subclass (the plugin's settings page registration accepts a config override; see README).

## [1.0.0] - 2026-04-26

### Added

- Initial public release.
- Filament-native register page extending `\Filament\Auth\Pages\Register`. Implements customisations entirely via documented Filament hooks (`form()`, `mutateFormDataBeforeRegister()`, `handleRegistration()`) — no `register()` reimplementation.
- Pluggable captcha pipeline: Cloudflare Turnstile, Google reCAPTCHA v3, and a null provider. Driven by a `CaptchaProvider` contract; new providers can be added without changing the page.
- `FilamentRegistrationPlugin` with chainable `defaultRole(string|null)` config. Logs a warning and skips role assignment if the configured role doesn't exist (so a misconfig doesn't block signups).
- `RegistrationSettings` Filament admin page for captcha configuration, gated through Filament Shield's `HasPageShield` trait (permission `View:RegistrationSettings`). Encrypts secret keys at rest.
- `Illuminate\Auth\Events\Registered` is dispatched alongside Filament's `Filament\Auth\Events\Registered`, so existing Laravel-event listeners keep firing.
- Container-bound `RegistrationResponse` (Filament's contract) so hosts can swap the post-register redirect — useful for onboarding flows.
- Honeypot field and a pre-captcha rate limit (30 attempts per IP per minute) to protect the captcha vendor from amplification.
- Idempotent migration for `tallcms_registration_settings` (guarded with `Schema::hasTable`); fresh installs create it, existing tables are left untouched.

### Behaviour notes for users migrating from `tallcms/tallcms-user-registration-plugin` v1.x

- **Honeypot**: previously returned a silent fake-success page on bot detection; now throws a validation error so the form shows a generic "Bot check failed" message.
- **Post-validation throttle**: previously a custom 5-per-minute throttle; now relies on Filament's built-in throttle (2 attempts per minute, 2 per email per minute).
- **Email verification**: previously had a custom `/registered` and `/awaiting-verification` flow; now defers entirely to Filament's `->emailVerification()` panel method.
- **Default-role missing**: previously aborted with a 500 error; now logs a warning and creates the user without a role.
