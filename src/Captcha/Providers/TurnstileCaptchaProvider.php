<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Captcha\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tallcms\FilamentRegistration\Captcha\Contracts\CaptchaProvider;

/**
 * Cloudflare Turnstile captcha provider.
 *
 * Turnstile renders a widget that the user interacts with (or that auto-passes
 * for trusted visitors); the widget writes a token into the form field
 * `cf-turnstile-response` automatically. Works cleanly inside Livewire forms
 * because the token is set by the time the user clicks submit — no
 * form-submit interception required.
 */
class TurnstileCaptchaProvider implements CaptchaProvider
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly string $siteKey,
        private readonly string $secretKey,
    ) {}

    public function isEnabled(): bool
    {
        return $this->siteKey !== '' && $this->secretKey !== '';
    }

    public function tokenField(): string
    {
        return 'cf-turnstile-response';
    }

    public function renderSnippet(): string
    {
        $siteKey = e($this->siteKey);

        // The data-callback hook writes the token into Livewire's form state
        // via `$wire.set(...)`. This is the bridge between Turnstile's native
        // hidden-input write and Filament's Livewire-bound form field.
        return <<<HTML
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<div
    class="cf-turnstile"
    data-sitekey="{$siteKey}"
    data-callback="filamentRegistrationTurnstileCallback"
></div>
<script>
    window.filamentRegistrationTurnstileCallback = function (token) {
        const wire = window.Livewire?.find(
            document.querySelector('[wire\\\\:id]')?.getAttribute('wire:id')
        );
        if (wire) {
            wire.set('data.cf-turnstile-response', token, false);
        }
    };
</script>
HTML;
    }

    public function verify(string $token, ?string $ip): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));

            if (! $response->successful()) {
                Log::debug('Turnstile verify HTTP error', ['status' => $response->status()]);

                return false;
            }

            return (bool) ($response->json('success') ?? false);
        } catch (\Throwable $e) {
            Log::debug('Turnstile verify threw', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
