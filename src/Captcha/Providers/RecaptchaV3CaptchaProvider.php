<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Captcha\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tallcms\FilamentRegistration\Captcha\Contracts\CaptchaProvider;

/**
 * Google reCAPTCHA v3 captcha provider.
 *
 * Unlike Turnstile, reCAPTCHA v3 has no visible widget — it scores the user
 * based on background signals and exposes the score via JavaScript. We obtain
 * a token on page load and refresh it periodically (tokens expire after 2
 * minutes), writing the latest token into Livewire's form state via
 * `$wire.set('data.g-recaptcha-response', token)` so it's present when the
 * user submits the Filament form.
 *
 * Verify-side: the host requires `score >= minScore` (default 0.5).
 */
class RecaptchaV3CaptchaProvider implements CaptchaProvider
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(
        private readonly string $siteKey,
        private readonly string $secretKey,
        private readonly float $minScore = 0.5,
    ) {}

    public function isEnabled(): bool
    {
        return $this->siteKey !== '' && $this->secretKey !== '';
    }

    public function tokenField(): string
    {
        return 'g-recaptcha-response';
    }

    public function renderSnippet(): string
    {
        $siteKey = e($this->siteKey);

        return <<<HTML
<script src="https://www.google.com/recaptcha/api.js?render={$siteKey}"></script>
<script>
    (function () {
        function fetchToken() {
            grecaptcha.ready(function () {
                grecaptcha.execute('{$siteKey}', { action: 'register' }).then(function (token) {
                    const el = document.querySelector('[wire\\\\:id]');
                    if (!el) return;
                    const wire = window.Livewire?.find(el.getAttribute('wire:id'));
                    if (wire) {
                        wire.set('data.g-recaptcha-response', token, false);
                    }
                });
            });
        }
        // Fetch on load, then refresh every 90s (tokens expire after 120s).
        fetchToken();
        setInterval(fetchToken, 90 * 1000);
    })();
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
                Log::debug('reCAPTCHA verify HTTP error', ['status' => $response->status()]);

                return false;
            }

            $data = $response->json();

            if (! ($data['success'] ?? false)) {
                return false;
            }

            $score = (float) ($data['score'] ?? 0);

            return $score >= $this->minScore;
        } catch (\Throwable $e) {
            Log::debug('reCAPTCHA verify threw', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
