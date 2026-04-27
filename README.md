# Filament Registration

A Filament v5 plugin that wires up Filament-native user registration with pluggable captcha (Cloudflare Turnstile, Google reCAPTCHA v3) and configurable default-role assignment.

Filament has built-in `->registration()` support, but the default flow is just name/email/password — no captcha, no role assignment, no event bridge for hosts that listen on Laravel's `Registered` event. This plugin fills those gaps as a drop-in replacement that still uses Filament's documented hooks (no `register()` reimplementation, no internal copy-paste).

## Features

- **Filament-native register page** that extends `\Filament\Auth\Pages\Register`. Login ↔ register cross-linking works for free.
- **Pluggable captcha pipeline**: Cloudflare Turnstile, Google reCAPTCHA v3, or none. Settings managed through a Filament admin page; secrets are encrypted at rest.
- **Default-role assignment** with a graceful fallback: if the configured role doesn't exist, registration still succeeds and a warning is logged (so a misconfig doesn't lose all signups).
- **Honeypot field** + pre-captcha throttle protect the captcha vendor from amplification.
- **Laravel `Illuminate\Auth\Events\Registered` is fired** alongside Filament's own `Filament\Auth\Events\Registered` event, so listeners written for Laravel's event keep working.
- **Container-bound redirect response** so hosts can swap the post-registration redirect (e.g. send new users into an onboarding flow) without subclassing the page.

## Installation

```bash
composer require tallcms/filament-registration
php artisan migrate
```

Add to your panel provider:

```php
use Tallcms\FilamentRegistration\Filament\FilamentRegistrationPlugin;
use Tallcms\FilamentRegistration\Filament\Pages\Register;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('admin')
        ->login()
        ->registration(Register::class)            // explicit page class — required
        ->plugin(
            FilamentRegistrationPlugin::make()
                ->defaultRole('member')            // optional; null = no role assignment
        );
}
```

**Both `->registration(Register::class)` and `->plugin(FilamentRegistrationPlugin::make())` are required** and not interchangeable. `->registration()` with no argument uses Filament's default page; the captcha and role assignment only run when the explicit class is wired.

Optional: enable Filament's built-in email verification with `->emailVerification()`. The plugin defers to it — when the panel requires verification, Filament sends the verification email; when it doesn't, new users are pre-marked verified.

## Configuration

After installation, navigate to **Admin → Settings → Registration** to configure captcha. Secrets are encrypted at rest. Site keys (public) and provider selection are stored alongside.

The captcha provider is detected from the saved settings; you don't need to wire each provider individually.

> **Heads up on fresh installs**: the settings page is gated by Filament Shield (uses the `HasPageShield` trait, generating a `View:RegistrationSettings` permission). On a fresh install the permission row doesn't exist yet, so the page is hidden from everyone — including super_admins. Run once after installing:
> ```bash
> php artisan shield:generate --page=RegistrationSettings --panel=<your-panel-id>
> ```
> This creates the permission and grants it to `super_admin`. Other roles get it via the Shield UI (Admin → Roles → pick role → Custom Permissions tab).

## Customising the post-register redirect

Bind a custom response in your service provider:

```php
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use App\Http\Responses\OnboardingRegistrationResponse;

public function register(): void
{
    $this->app->bind(RegistrationResponse::class, OnboardingRegistrationResponse::class);
}
```

Your concrete just needs to implement `RegistrationResponse` (which extends `Responsable`) and return a `RedirectResponse` from `toResponse()`.

## Behaviour notes

- **Honeypot**: a non-empty honeypot field returns a validation error, not a fake-success page. Bots see the form bounce; humans never trigger it.
- **Throttling**: pre-captcha throttle is 30 attempts per IP per minute (protects the captcha vendor). Filament's built-in throttle (2 attempts per minute, 2 per email per minute) covers the post-validation case.
- **Email verification**: defers to Filament's `->emailVerification()` panel method. No custom verification view is shipped.

## Compatibility

- PHP 8.2+
- Filament v5+
- spatie/laravel-permission v6+ (for role assignment)

## License

MIT.
