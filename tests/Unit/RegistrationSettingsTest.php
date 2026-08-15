<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Tests\Unit;

use Tallcms\FilamentRegistration\Filament\Pages\RegistrationSettings;
use Tallcms\FilamentRegistration\Tests\TestCase;

class RegistrationSettingsTest extends TestCase
{
    public function test_default_navigation_label_and_title_are_translated(): void
    {
        $this->assertSame(
            __('filament-registration::messages.navigation_label'),
            RegistrationSettings::getNavigationLabel()
        );
        $this->assertSame(
            __('filament-registration::messages.title'),
            (new RegistrationSettings)->getTitle()
        );
    }

    public function test_subclass_can_override_navigation_label_and_title(): void
    {
        $subclass = new class extends RegistrationSettings
        {
            protected static ?string $navigationLabel = 'Bot Protection';

            protected static ?string $title = 'Bot Protection Settings';
        };

        $this->assertSame('Bot Protection', $subclass::getNavigationLabel());
        $this->assertSame('Bot Protection Settings', $subclass->getTitle());
    }
}
