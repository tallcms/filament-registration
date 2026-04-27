<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Captcha;

use Illuminate\Support\Facades\Log;
use Tallcms\FilamentRegistration\Captcha\Contracts\CaptchaProvider;
use Tallcms\FilamentRegistration\Captcha\Providers\NullCaptchaProvider;
use Tallcms\FilamentRegistration\Captcha\Providers\RecaptchaV3CaptchaProvider;
use Tallcms\FilamentRegistration\Captcha\Providers\TurnstileCaptchaProvider;

/**
 * Resolves the active CaptchaProvider from runtime config.
 *
 * Reads from `config('filament-registration.captcha.*')` keys. The plugin's
 * service provider merges DB-stored settings into config at boot, so the
 * source of truth at this point is just config.
 */
class CaptchaManager
{
    public function resolve(): CaptchaProvider
    {
        $siteKey = (string) config('filament-registration.captcha.site_key', '');
        $secretKey = (string) config('filament-registration.captcha.secret_key', '');

        if (! self::resolveEnabled($siteKey, $secretKey)) {
            return new NullCaptchaProvider;
        }

        if ($siteKey === '' || $secretKey === '') {
            if (app()->isProduction()) {
                Log::warning('FilamentRegistration: CAPTCHA enabled but site_key or secret_key is missing — falling back to NullCaptchaProvider.');
            }

            return new NullCaptchaProvider;
        }

        $provider = (string) config('filament-registration.captcha.provider', 'turnstile');

        return match ($provider) {
            'recaptcha_v3' => new RecaptchaV3CaptchaProvider(
                $siteKey,
                $secretKey,
                (float) config('filament-registration.captcha.recaptcha_min_score', 0.5),
            ),
            default => new TurnstileCaptchaProvider($siteKey, $secretKey),
        };
    }

    /**
     * The `captcha.enabled` truth table:
     * - Explicit env FILAMENT_REGISTRATION_CAPTCHA_ENABLED wins when set (truthy/falsy).
     * - When unset (null), auto-enable iff both site_key and secret_key are non-empty.
     */
    public static function resolveEnabled(string $siteKey, string $secretKey): bool
    {
        $explicit = config('filament-registration.captcha.enabled');

        if ($explicit !== null) {
            return filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
        }

        return $siteKey !== '' && $secretKey !== '';
    }
}
