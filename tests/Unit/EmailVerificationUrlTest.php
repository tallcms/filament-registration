<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Tests\Unit;

use Illuminate\Auth\Notifications\VerifyEmail;
use Tallcms\FilamentRegistration\Providers\FilamentRegistrationServiceProvider;
use Tallcms\FilamentRegistration\Tests\TestCase;

/**
 * Regression coverage for https://github.com/tallcms/filament-registration/issues/3:
 * registering with `->emailVerification()` required threw
 * `RouteNotFoundException: Route [verification.verify] not defined` because
 * Laravel's default `MustVerifyEmail::sendEmailVerificationNotification()`
 * resolves its link via `route('verification.verify')`, a route
 * Filament-only apps never register.
 */
class EmailVerificationUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        VerifyEmail::$createUrlCallback = null;

        parent::tearDown();
    }

    public function test_service_provider_registers_a_verify_email_url_callback(): void
    {
        $this->assertNotNull(VerifyEmail::$createUrlCallback);
    }

    public function test_it_does_not_overwrite_a_host_defined_url_callback(): void
    {
        $callback = fn (mixed $notifiable): string => 'https://example.test/custom-verify';

        VerifyEmail::createUrlUsing($callback);

        (new FilamentRegistrationServiceProvider($this->app))->boot();

        $this->assertSame($callback, VerifyEmail::$createUrlCallback);
    }
}
