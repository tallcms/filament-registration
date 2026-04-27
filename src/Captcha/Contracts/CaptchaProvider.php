<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Captcha\Contracts;

interface CaptchaProvider
{
    /**
     * Whether this provider is enabled and ready to verify tokens.
     * A provider returning false is treated as "no captcha" by the register page.
     */
    public function isEnabled(): bool;

    /**
     * The form field name the provider's client widget writes the token into.
     * The Register page binds a Hidden form field at this name and reads it
     * during verify().
     */
    public function tokenField(): string;

    /**
     * Raw HTML/JS to embed alongside the form so the captcha widget renders.
     * The output is rendered via Filament's `Html::make($snippet)` form
     * component, which embeds it without escaping. Must be safe (provider
     * implementations escape their own dynamic values).
     */
    public function renderSnippet(): string;

    /**
     * Validate the token submitted by the client. Implementations should be
     * defensive — never throw; return false on any error or timeout.
     */
    public function verify(string $token, ?string $ip): bool;
}
