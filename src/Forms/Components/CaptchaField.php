<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Forms\Components;

use Filament\Forms\Components\Field;
use Tallcms\FilamentRegistration\Captcha\Contracts\CaptchaProvider;

/**
 * A composite Filament form field that renders a captcha widget.
 *
 * Internally the field's view emits two things:
 *   1. The provider's `renderSnippet()` HTML/JS (loads the widget, hooks
 *      the token into Livewire state via $wire.set).
 *   2. A Livewire-bound hidden input at the provider's `tokenField()` so the
 *      token reaches `$this->form->getState()` and survives validation.
 *
 * If the active provider is the NullCaptchaProvider (i.e. captcha disabled),
 * the field renders nothing — Filament's form pipeline still sees the field,
 * but the user sees no widget and `verify()` accepts any token.
 */
class CaptchaField extends Field
{
    protected string $view = 'filament-registration::components.captcha-field';

    protected CaptchaProvider $provider;

    public static function make(?string $name = null): static
    {
        $provider = app(CaptchaProvider::class);

        $static = parent::make($name ?? $provider->tokenField());
        $static->provider = $provider;
        $static->dehydrated();

        // Set an accessible label and hide it visually. Without this, Filament
        // derives the label from the snake-case field name (e.g. the Turnstile
        // token field "cf-turnstile-response") which humanises to a confusing
        // "Cf turnstile response" heading above the widget. The widget itself
        // is self-explanatory, so the label is hidden — but it's still set so
        // screen readers and validation messages have a sensible identity.
        $static->label(__('Verification'));
        $static->hiddenLabel();

        return $static;
    }

    public function getProvider(): CaptchaProvider
    {
        return $this->provider;
    }

    public function getRenderedSnippet(): string
    {
        return $this->provider->renderSnippet();
    }

    public function getTokenField(): string
    {
        return $this->provider->tokenField();
    }
}
