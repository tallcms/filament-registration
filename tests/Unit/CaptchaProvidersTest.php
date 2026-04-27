<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Tallcms\FilamentRegistration\Captcha\CaptchaManager;
use Tallcms\FilamentRegistration\Captcha\Providers\NullCaptchaProvider;
use Tallcms\FilamentRegistration\Captcha\Providers\RecaptchaV3CaptchaProvider;
use Tallcms\FilamentRegistration\Captcha\Providers\TurnstileCaptchaProvider;
use Tallcms\FilamentRegistration\Tests\TestCase;

class CaptchaProvidersTest extends TestCase
{
    public function test_null_provider_accepts_any_token(): void
    {
        $provider = new NullCaptchaProvider;

        $this->assertFalse($provider->isEnabled());
        $this->assertTrue($provider->verify('whatever', '127.0.0.1'));
        $this->assertSame('', $provider->renderSnippet());
    }

    public function test_turnstile_verify_returns_true_for_success_response(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $provider = new TurnstileCaptchaProvider('site-key', 'secret-key');

        $this->assertTrue($provider->isEnabled());
        $this->assertTrue($provider->verify('valid-token', '127.0.0.1'));
        $this->assertSame('cf-turnstile-response', $provider->tokenField());
    }

    public function test_turnstile_verify_returns_false_on_failure(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false], 200),
        ]);

        $provider = new TurnstileCaptchaProvider('site-key', 'secret-key');

        $this->assertFalse($provider->verify('bogus-token', '127.0.0.1'));
    }

    public function test_turnstile_verify_returns_false_on_empty_token(): void
    {
        Http::fake();

        $provider = new TurnstileCaptchaProvider('site-key', 'secret-key');

        $this->assertFalse($provider->verify('', '127.0.0.1'));
        Http::assertNothingSent();
    }

    public function test_turnstile_verify_returns_false_on_http_error(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response('', 500),
        ]);

        $provider = new TurnstileCaptchaProvider('site-key', 'secret-key');

        $this->assertFalse($provider->verify('valid-token', '127.0.0.1'));
    }

    public function test_recaptcha_v3_verify_passes_when_score_meets_minimum(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.7], 200),
        ]);

        $provider = new RecaptchaV3CaptchaProvider('site-key', 'secret-key', 0.5);

        $this->assertTrue($provider->verify('token', '127.0.0.1'));
        $this->assertSame('g-recaptcha-response', $provider->tokenField());
    }

    public function test_recaptcha_v3_verify_fails_when_score_below_minimum(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.3], 200),
        ]);

        $provider = new RecaptchaV3CaptchaProvider('site-key', 'secret-key', 0.5);

        $this->assertFalse($provider->verify('token', '127.0.0.1'));
    }

    public function test_recaptcha_v3_verify_fails_when_success_is_false(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response(['success' => false, 'score' => 0.9], 200),
        ]);

        $provider = new RecaptchaV3CaptchaProvider('site-key', 'secret-key', 0.5);

        $this->assertFalse($provider->verify('token', '127.0.0.1'));
    }

    public function test_manager_resolves_null_provider_when_keys_missing(): void
    {
        config([
            'filament-registration.captcha.enabled' => true,
            'filament-registration.captcha.site_key' => '',
            'filament-registration.captcha.secret_key' => '',
        ]);

        $provider = (new CaptchaManager)->resolve();

        $this->assertInstanceOf(NullCaptchaProvider::class, $provider);
    }

    public function test_manager_resolves_turnstile_by_default(): void
    {
        config([
            'filament-registration.captcha.enabled' => true,
            'filament-registration.captcha.provider' => 'turnstile',
            'filament-registration.captcha.site_key' => 'sk',
            'filament-registration.captcha.secret_key' => 'ss',
        ]);

        $provider = (new CaptchaManager)->resolve();

        $this->assertInstanceOf(TurnstileCaptchaProvider::class, $provider);
    }

    public function test_manager_resolves_recaptcha_v3_when_configured(): void
    {
        config([
            'filament-registration.captcha.enabled' => true,
            'filament-registration.captcha.provider' => 'recaptcha_v3',
            'filament-registration.captcha.site_key' => 'sk',
            'filament-registration.captcha.secret_key' => 'ss',
        ]);

        $provider = (new CaptchaManager)->resolve();

        $this->assertInstanceOf(RecaptchaV3CaptchaProvider::class, $provider);
    }

    public function test_manager_disables_when_explicit_false(): void
    {
        config([
            'filament-registration.captcha.enabled' => false,
            'filament-registration.captcha.site_key' => 'sk',
            'filament-registration.captcha.secret_key' => 'ss',
        ]);

        $provider = (new CaptchaManager)->resolve();

        $this->assertInstanceOf(NullCaptchaProvider::class, $provider);
    }
}
