<?php

/*
 * Internal config file for tallcms/filament-registration.
 *
 * IMPORTANT: env() calls live here, not in the service provider, so they
 * survive `php artisan config:cache`. Calling env() from a service
 * provider's register() method returns null on production deploys with
 * config caching enabled (Laravel intentionally skips .env loading once
 * config is cached). Putting env() in a config file means the values are
 * resolved AT cache-time and baked into bootstrap/cache/config.php.
 *
 * The file is loaded via mergeConfigFrom() in the service provider — not
 * publishable by hosts, since the plugin doesn't expose a publishable
 * config layer (use env vars or the Admin UI to configure).
 */

return [
    'captcha' => [
        // null = auto (enable iff site_key and secret_key are both present);
        // explicit true/false from env wins.
        'enabled' => env('FILAMENT_REGISTRATION_CAPTCHA_ENABLED'),
        'provider' => env('FILAMENT_REGISTRATION_CAPTCHA_PROVIDER', 'turnstile'),
        'site_key' => env('FILAMENT_REGISTRATION_CAPTCHA_SITE_KEY', ''),
        'secret_key' => env('FILAMENT_REGISTRATION_CAPTCHA_SECRET_KEY', ''),
        'recaptcha_min_score' => (float) env('FILAMENT_REGISTRATION_CAPTCHA_RECAPTCHA_MIN_SCORE', 0.5),
    ],
];
