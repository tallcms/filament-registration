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

    public function getId(): string
    {
        return 'filament-registration';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            RegistrationSettings::class,
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
}
