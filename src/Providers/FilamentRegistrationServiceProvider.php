<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Providers;

use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\ServiceProvider;
use Tallcms\FilamentRegistration\Captcha\CaptchaManager;
use Tallcms\FilamentRegistration\Captcha\Contracts\CaptchaProvider;
use Tallcms\FilamentRegistration\Http\Responses\RegistrationResponse;
use Tallcms\FilamentRegistration\Services\SettingsRepository;

class FilamentRegistrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Two-layer config: env vars provide defaults via the package's
        // internal config file, the Admin UI (DB) overlays on top during
        // boot(). The plugin doesn't ship a publishable config — hosts
        // configure via env or the admin UI.
        //
        // mergeConfigFrom is critical for production: it's a no-op when
        // config:cache is active (Laravel skips it to use the cached file),
        // and the cached file already has env() values baked in from the
        // last time config:cache ran. Calling env() directly in this
        // method instead would return null on production once cache runs.
        $this->mergeConfigFrom(
            __DIR__.'/../../config/filament-registration.php',
            'filament-registration'
        );

        // Load early so Filament nav labels resolve before nested providers boot.
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'filament-registration');

        $this->app->singleton(SettingsRepository::class);
        $this->app->singleton(CaptchaManager::class);

        // Each call resolves a fresh provider so settings updates take effect
        // without restarting workers; the manager is cheap.
        $this->app->bind(CaptchaProvider::class, fn ($app) => $app->make(CaptchaManager::class)->resolve());

        // Default post-registration response. Hosts override by binding the
        // same Filament contract to a different concrete in their own
        // service provider (e.g. an onboarding-aware redirect).
        $this->app->bind(RegistrationResponseContract::class, RegistrationResponse::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'filament-registration');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->mergeDbSettingsIntoConfig();
        $this->configureEmailVerificationUrl();
    }

    /**
     * Point Laravel's default `MustVerifyEmail` notification at Filament's
     * panel-scoped verification route instead of the `verification.verify`
     * route name.
     *
     * `Register::handleRegistration()` fires Laravel's `Illuminate\Auth\
     * Events\Registered` event (see that method's docblock) so non-Filament
     * listeners keep working. That also triggers Laravel's own
     * auto-registered `SendEmailVerificationNotification` listener, which
     * calls `$user->sendEmailVerificationNotification()` — the stock
     * `MustVerifyEmail` trait method builds `Illuminate\Auth\Notifications\
     * VerifyEmail`, and that notification resolves its link via
     * `route('verification.verify')`. Filament-only apps never register
     * that route (they use panel-scoped verification routes instead), so
     * every registration with `->emailVerification()` required threw
     * `RouteNotFoundException` before this fix.
     *
     * Guarded so a host that already customised `VerifyEmail::createUrlUsing()`
     * (e.g. because it also runs a non-Filament auth flow with its own
     * `verification.verify` route) keeps its own behaviour.
     */
    private function configureEmailVerificationUrl(): void
    {
        if (VerifyEmail::$createUrlCallback !== null) {
            return;
        }

        VerifyEmail::createUrlUsing(
            fn (mixed $notifiable): string => Filament::getVerifyEmailUrl($notifiable)
        );
    }

    /**
     * Pull DB-stored settings (managed via Filament admin) into runtime
     * config so the captcha manager picks them up.
     *
     * Empty-string and null DB values are skipped so they don't clobber
     * env-loaded defaults. Without this guard, saving the form with a
     * blank site_key (e.g. when the user hasn't filled it yet but pressed
     * Save to set the secret) would override a perfectly good
     * FILAMENT_REGISTRATION_CAPTCHA_SITE_KEY env var with an empty string
     * and fall back to NullCaptchaProvider.
     *
     * Booleans (including false), numbers (including 0), and non-empty
     * strings all still override env — empty/null is the one signal we
     * treat as "not set; let env win".
     */
    private function mergeDbSettingsIntoConfig(): void
    {
        try {
            $stored = app(SettingsRepository::class)->all();
        } catch (\Throwable $e) {
            return;
        }

        if ($stored === []) {
            return;
        }

        $map = [
            'captcha_enabled' => 'filament-registration.captcha.enabled',
            'captcha_provider' => 'filament-registration.captcha.provider',
            'captcha_site_key' => 'filament-registration.captcha.site_key',
            'captcha_secret_key' => 'filament-registration.captcha.secret_key',
            'captcha_recaptcha_min_score' => 'filament-registration.captcha.recaptcha_min_score',
        ];

        foreach ($map as $dbKey => $configKey) {
            if (! array_key_exists($dbKey, $stored)) {
                continue;
            }

            $value = $stored[$dbKey];

            if ($value === null || $value === '') {
                continue;
            }

            config([$configKey => $value]);
        }
    }
}
