<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Tallcms\FilamentRegistration\Filament\Pages\RegistrationSettings;

/**
 * Filament plugin entry point.
 *
 * Host wires it up like:
 *
 * ```php
 * $panel
 *     ->registration(\Tallcms\FilamentRegistration\Filament\Pages\Register::class)
 *     ->plugin(
 *         FilamentRegistrationPlugin::make()
 *             ->defaultRole('site_owner')
 *     );
 * ```
 *
 * Both calls are required: `->registration(Register::class)` tells Filament
 * which page handles the route; `->plugin(...)` registers the admin settings
 * page and exposes the chainable config.
 */
class FilamentRegistrationPlugin implements Plugin
{
    protected ?string $defaultRole = null;

    /** @var class-string<RegistrationSettings> */
    protected string $settingsPage = RegistrationSettings::class;

    public function getId(): string
    {
        return 'filament-registration';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            $this->settingsPage,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament('filament-registration');

        return $plugin;
    }

    /**
     * Set the role assigned to new users on registration.
     * Pass null (or skip the call) to disable role assignment.
     *
     * If the configured role doesn't exist when registration runs, the user
     * is still created and a warning is logged — a misconfig won't block
     * signups.
     */
    public function defaultRole(?string $role): static
    {
        $this->defaultRole = $role;

        return $this;
    }

    public function getDefaultRole(): ?string
    {
        return $this->defaultRole;
    }

    /**
     * Swap in a host-defined settings page subclass — typically one that adds
     * `BezhanSalleh\FilamentShield\Traits\HasPageShield` for permission gating
     * (or any other host-specific access control).
     *
     * Pass a class name extending `Tallcms\FilamentRegistration\Filament\Pages\RegistrationSettings`.
     * Skip the call to keep the package's default page (no Shield gating, public
     * to anyone who can access the panel).
     *
     * @param class-string<RegistrationSettings> $class
     */
    public function settingsPage(string $class): static
    {
        if (! is_subclass_of($class, RegistrationSettings::class) && $class !== RegistrationSettings::class) {
            throw new \InvalidArgumentException(
                "Settings page class must extend ".RegistrationSettings::class.", got {$class}"
            );
        }

        $this->settingsPage = $class;

        return $this;
    }

    /**
     * @return class-string<RegistrationSettings>
     */
    public function getSettingsPage(): string
    {
        return $this->settingsPage;
    }
}
