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

### Authorization for the settings page

By default the page is accessible to any user who can reach the panel (i.e. anyone who passes the panel's auth middleware). The plugin doesn't ship a built-in permission gate because Filament users have very different auth setups (some use Shield, some Bouncer, some plain canAccess(), some panel-level middleware). Pick whichever fits your app:

#### Using [TallCMS](https://tallcms.com)? (zero config)

[TallCMS](https://github.com/tallcms/tallcms) is a Laravel + Filament CMS built on the TALL stack. If you're using it, install the [`tallcms/registration`](https://github.com/tallcms/user-registration-plugin) bridge plugin and you're done — the bridge ships with sensible defaults out of the box:

- Shield permission gating on the settings page (auto-creates `View:RegistrationSettings`, granted to `super_admin` by default)
- Default role `site_owner` with role-aware policy scoping
- Onboarding redirect into the Multisite Template Gallery for new users
- Default site-plan assignment (Multisite plugin integration)
- Themed login + register pages that match your active theme

```bash
# In a TallCMS install
composer require tallcms/filament-registration
php artisan migrate
# Then upload the tallcms/registration bridge plugin via Admin → Plugins
```

No subclassing, no `canAccess()` override — TallCMS does it for you. Read more at [tallcms.com](https://tallcms.com).

#### Filament Shield users

Subclass the page and add the trait, then point your panel at the subclass:

```php
namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Tallcms\FilamentRegistration\Filament\Pages\RegistrationSettings as BaseRegistrationSettings;

class RegistrationSettings extends BaseRegistrationSettings
{
    use HasPageShield;
}
```

Then run `php artisan shield:generate --page=RegistrationSettings --panel=<id>` once and grant the resulting `View:RegistrationSettings` permission to roles via the Shield UI.

#### Plain `canAccess()` users

Subclass and override:

```php
public static function canAccess(): bool
{
    return auth()->user()?->is_admin ?? false;
}
```

**Optional dependency note**: role assignment via `defaultRole(...)` requires `spatie/laravel-permission` (suggested in `composer.json`). Hosts without Spatie permissions get role assignment as a no-op — no fatal error, the user is still created.

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
