<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Tests\Unit;

use Illuminate\Auth\Events\Registered as LaravelRegistered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Tallcms\FilamentRegistration\Filament\Pages\Register;
use Tallcms\FilamentRegistration\Tests\TestCase;

/**
 * Regression coverage for https://github.com/tallcms/filament-registration/issues/3:
 * once the `RouteNotFoundException` crash is fixed (see EmailVerificationUrlTest),
 * both Laravel's built-in `SendEmailVerificationNotification` listener (triggered
 * by the `LaravelRegistered` bridge in Register::handleRegistration()) and
 * Filament's own `sendEmailVerificationNotification()` would send a verification
 * email, duplicating it. `Register::isLaravelVerificationListenerRegistered()`
 * detects when the former will handle it so the latter can defer.
 */
class RegisterVerificationListenerTest extends TestCase
{
    public function test_detects_the_default_laravel_listener_when_registered(): void
    {
        Event::listen(LaravelRegistered::class, SendEmailVerificationNotification::class);

        $page = new class extends Register
        {
            public function checkIsLaravelVerificationListenerRegistered(): bool
            {
                return $this->isLaravelVerificationListenerRegistered();
            }
        };

        $this->assertTrue($page->checkIsLaravelVerificationListenerRegistered());
    }

    public function test_reports_false_when_no_listener_is_registered(): void
    {
        $page = new class extends Register
        {
            public function checkIsLaravelVerificationListenerRegistered(): bool
            {
                return $this->isLaravelVerificationListenerRegistered();
            }
        };

        $this->assertFalse($page->checkIsLaravelVerificationListenerRegistered());
    }
}
