<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Captcha\Providers;

use Tallcms\FilamentRegistration\Captcha\Contracts\CaptchaProvider;

/**
 * No-op provider used when captcha is disabled or misconfigured.
 * `verify()` always returns true so registration continues without
 * a captcha gate.
 */
class NullCaptchaProvider implements CaptchaProvider
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function tokenField(): string
    {
        return '_captcha_token';
    }

    public function renderSnippet(): string
    {
        return '';
    }

    public function verify(string $token, ?string $ip): bool
    {
        return true;
    }
}
