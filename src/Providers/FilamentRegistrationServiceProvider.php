<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Providers;

use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Illuminate\Support\ServiceProvider;
use Tallcms\FilamentRegistration\Captcha\CaptchaManager;
use Tallcms\FilamentRegistration\Captcha\Contracts\CaptchaProvider;
use Tallcms\FilamentRegistration\Http\Responses\RegistrationResponse;
use Tallcms\FilamentRegistration\Services\SettingsRepository;

class FilamentRegistrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Two-layer config: env vars provide defaults, the Admin UI (DB)
        // overlays on top during boot(). The plugin doesn't ship a publishable
        // config/filament-registration.php — settings live in env or in the
        // tallcms_registration_settings table.
        config(['filament-registration' => [
            'captcha' => [
                // null = auto (enable iff site_key and secret_key are both present);
                // explicit true/false from env wins.
                'enabled' => env('FILAMENT_REGISTRATION_CAPTCHA_ENABLED'),
                'provider' => env('FILAMENT_REGISTRATION_CAPTCHA_PROVIDER', 'turnstile'),
                'site_key' => env('FILAMENT_REGISTRATION_CAPTCHA_SITE_KEY', ''),
                'secret_key' => env('FILAMENT_REGISTRATION_CAPTCHA_SECRET_KEY', ''),
                'recaptcha_min_score' => (float) env('FILAMENT_REGISTRATION_CAPTCHA_RECAPTCHA_MIN_SCORE', 0.5),
            ],
        ]]);

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
