# Changelog

All notable changes to `tallcms/filament-registration` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
