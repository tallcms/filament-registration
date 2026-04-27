<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Filament\Pages;

use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Schema;
use Illuminate\Auth\Events\Registered as LaravelRegistered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tallcms\FilamentRegistration\Captcha\Contracts\CaptchaProvider;
use Tallcms\FilamentRegistration\Filament\FilamentRegistrationPlugin;
use Tallcms\FilamentRegistration\Forms\Components\CaptchaField;

/**
 * Custom Filament Register page that adds:
 *  - a honeypot field (anti-bot)
 *  - a pluggable captcha widget + verify
 *  - default-role assignment after user creation
 *  - a Laravel `Registered` event bridge so listeners written for Laravel's
 *    event keep firing (Filament fires its own `Filament\Auth\Events\Registered`)
 *
 * All customisation lives inside Filament's documented hooks
 * (`form()`, `mutateFormDataBeforeRegister()`, `handleRegistration()`).
 * We don't override `register()` — the parent's body handles validation,
 * throttling, hook dispatch, login, and redirect. That keeps us future-proof
 * across Filament patch releases.
 */
class Register extends BaseRegister
{
    /**
     * Field name for the honeypot input. Must not be a real User column;
     * a non-empty value triggers a validation error and stops registration.
     */
    public const HONEYPOT_FIELD = '_honeypot';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getHoneypotFormComponent(),
            $this->getCaptchaFormComponent(),
        ]);
    }

    protected function getHoneypotFormComponent(): Hidden
    {
        // Filament renders Hidden as `<input type="hidden">`, which bots happily
        // fill via auto-form-fill. Real users never touch it; non-empty value
        // on submit = bot. Filament only renders the input if it's part of the
        // form schema, so we add it explicitly here.
        return Hidden::make(self::HONEYPOT_FIELD)
            ->dehydrated();
    }

    protected function getCaptchaFormComponent(): CaptchaField
    {
        return CaptchaField::make();
    }

    /**
     * Pre-create gate. All failure paths throw Laravel's ValidationException
     * — Filament catches it and surfaces messages on the form.
     */
    public function mutateFormDataBeforeRegister(array $data): array
    {
        // 1. Honeypot — bots fill hidden inputs; humans don't.
        if (! empty($data[self::HONEYPOT_FIELD] ?? null)) {
            throw ValidationException::withMessages([
                'data.'.self::HONEYPOT_FIELD => __('Bot check failed. Please try again.'),
            ]);
        }
        unset($data[self::HONEYPOT_FIELD]);

        $captcha = app(CaptchaProvider::class);

        // Captcha disabled / NullProvider — strip the token field if it
        // somehow snuck in and skip verification entirely.
        if (! $captcha->isEnabled()) {
            unset($data[$captcha->tokenField()]);

            return $data;
        }

        $tokenField = $captcha->tokenField();
        $token = (string) ($data[$tokenField] ?? '');

        // 2. Pre-captcha throttle — 30 attempts per IP per minute. Protects
        // the captcha vendor from amplification by attackers who'd otherwise
        // burn the verify-side rate limit on us.
        $throttleKey = 'captcha:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 30)) {
            throw ValidationException::withMessages([
                'data.'.$tokenField => __('Too many attempts. Please wait a minute and try again.'),
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        // 3. Captcha verify.
        if (! $captcha->verify($token, request()->ip())) {
            throw ValidationException::withMessages([
                'data.'.$tokenField => __('Captcha verification failed. Please try again.'),
            ]);
        }

        // 4. Strip the captcha token so it doesn't reach User::create().
        unset($data[$tokenField]);

        return $data;
    }

    /**
     * Post-create work. Order matters:
     *  1. Let Filament create the user.
     *  2. Pre-mark verified if the panel doesn't require verification.
     *  3. Assign default role (if configured and the role exists).
     *  4. Bridge to Laravel's Registered event so non-Filament listeners fire.
     */
    public function handleRegistration(array $data): Model
    {
        $user = parent::handleRegistration($data);

        // 2. Email-verification short-circuit. If the User model implements
        // MustVerifyEmail but the panel is configured without
        // ->emailVerification(), pre-mark verified so Filament doesn't send
        // a verification email (its sendEmailVerificationNotification() only
        // sends if ! $user->hasVerifiedEmail()).
        if (
            $user instanceof MustVerifyEmail
            && ! Filament::getCurrentPanel()->isEmailVerificationRequired()
            && ! $user->hasVerifiedEmail()
        ) {
            $user->markEmailAsVerified();
        }

        // 3. Default-role assignment. Three guards before assigning:
        //   a) plugin has a default role configured
        //   b) the User model exposes assignRole() (Spatie HasRoles trait)
        //   c) the spatie/laravel-permission Role model class exists
        // (a) skip + warn if the role doesn't exist (per the plugin's
        // documented behaviour — a misconfig shouldn't block signups).
        // (b) and (c) make spatie/laravel-permission a soft dependency:
        // hosts that don't use Spatie roles get role assignment as a no-op
        // instead of a fatal class-not-found.
        $defaultRole = FilamentRegistrationPlugin::get()->getDefaultRole();
        $roleClass = '\\Spatie\\Permission\\Models\\Role';

        if (
            $defaultRole !== null
            && method_exists($user, 'assignRole')
            && class_exists($roleClass)
        ) {
            if ($roleClass::query()->where('name', $defaultRole)->exists()) {
                $user->assignRole($defaultRole);
            } else {
                Log::warning(sprintf(
                    'FilamentRegistration: configured default role "%s" does not exist; skipping role assignment for user #%s',
                    $defaultRole,
                    $user->getKey() ?? '?',
                ));
            }
        }

        // 4. Laravel event bridge. Filament fires its own
        // `Filament\Auth\Events\Registered` later in `register()`; we also
        // dispatch Laravel's so listeners written against the framework
        // standard event keep working.
        event(new LaravelRegistered($user));

        return $user;
    }
}
