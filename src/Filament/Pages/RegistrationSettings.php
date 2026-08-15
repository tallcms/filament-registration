<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Tallcms\FilamentRegistration\Captcha\CaptchaManager;
use Tallcms\FilamentRegistration\Captcha\Contracts\CaptchaProvider;
use Tallcms\FilamentRegistration\Services\SettingsRepository;

/**
 * Filament admin page for captcha configuration.
 *
 * **Authorization is the host's responsibility.** By default this page is
 * accessible to any user who can reach the panel (i.e. authenticated users
 * who pass the panel's auth middleware). Hosts that want stricter gating
 * have several drop-in options:
 *
 *   1. **Filament Shield**: subclass this page in the host app and add
 *      `use BezhanSalleh\FilamentShield\Traits\HasPageShield;`. Then wire
 *      the subclass via the plugin's `settingsPage(YourSettingsPage::class)`.
 *   2. **Plain canAccess()**: subclass and override `canAccess()` with any
 *      custom check (`auth()->user()->is_admin`, role check, etc.).
 *   3. **Panel middleware**: add the standard Laravel/Filament middleware
 *      to the panel's `->authMiddleware([...])` list.
 *
 * Coupling this class to Filament Shield by default would force every
 * Filament user installing the plugin to also install Shield — too
 * opinionated for a generic community plugin.
 */
class RegistrationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament-registration::filament.pages.registration-settings';

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('filament-registration::messages.navigation_label');
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-registration::messages.title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-registration::messages.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    protected function getFormStatePath(): string
    {
        return 'data';
    }

    public function mount(): void
    {
        $repo = app(SettingsRepository::class);

        $this->form->fill([
            'captcha_enabled' => (bool) ($repo->get('captcha_enabled') ?? config('filament-registration.captcha.enabled') ?? false),
            'captcha_provider' => (string) ($repo->get('captcha_provider') ?? config('filament-registration.captcha.provider', 'turnstile')),
            'captcha_site_key' => (string) ($repo->get('captcha_site_key') ?? config('filament-registration.captcha.site_key', '')),
            'captcha_secret_key' => '', // Never pre-fill; UI uses "leave blank = keep current" semantics
            'captcha_recaptcha_min_score' => (float) ($repo->get('captcha_recaptcha_min_score') ?? config('filament-registration.captcha.recaptcha_min_score', 0.5)),
        ]);
    }

    protected function getFormSchema(): array
    {
        $repo = app(SettingsRepository::class);
        $secretInDb = $repo->hasSecret('captcha_secret_key');
        // Read from config, never env() — config() works under config:cache,
        // env() returns null in cached production deploys.
        $secretInConfig = (string) config('filament-registration.captcha.secret_key', '') !== '';
        $secretFromOutsideDb = $secretInConfig && ! $secretInDb;
        $secretConfigured = $secretInDb || $secretInConfig;

        $secretHelper = match (true) {
            $secretInDb => __('filament-registration::messages.secret_help_in_db'),
            $secretFromOutsideDb => __('filament-registration::messages.secret_help_in_env'),
            default => __('filament-registration::messages.secret_help_default'),
        };

        return [
            Section::make(__('filament-registration::messages.section_captcha'))
                ->description(__('filament-registration::messages.section_captcha_description'))
                ->schema([
                    Toggle::make('captcha_enabled')
                        ->label(__('filament-registration::messages.enable_captcha'))
                        ->helperText(__('filament-registration::messages.enable_captcha_help')),

                    Select::make('captcha_provider')
                        ->label(__('filament-registration::messages.provider'))
                        ->options([
                            'turnstile' => __('filament-registration::messages.provider_turnstile'),
                            'recaptcha_v3' => __('filament-registration::messages.provider_recaptcha_v3'),
                        ])
                        ->required()
                        ->live()
                        ->helperText(new HtmlString(
                            __('filament-registration::messages.provider_help', [
                                'turnstile_url' => 'https://dash.cloudflare.com/?to=/:account/turnstile',
                                'recaptcha_url' => 'https://www.google.com/recaptcha/admin',
                            ])
                        )),

                    TextInput::make('captcha_site_key')
                        ->label(__('filament-registration::messages.site_key'))
                        ->helperText(__('filament-registration::messages.site_key_help'))
                        ->maxLength(255),

                    Placeholder::make('captcha_secret_status')
                        ->label(__('filament-registration::messages.secret_status'))
                        ->content(fn () => new HtmlString(
                            $secretConfigured
                                ? '<span class="text-success font-medium">'.e(__('filament-registration::messages.secret_configured')).'</span>'
                                : '<span class="text-warning font-medium">'.e(__('filament-registration::messages.secret_not_set')).'</span>'
                        )),

                    TextInput::make('captcha_secret_key')
                        ->label($secretConfigured
                            ? __('filament-registration::messages.replace_secret_key')
                            : __('filament-registration::messages.secret_key'))
                        ->password()
                        ->revealable()
                        ->placeholder($secretConfigured
                            ? '••••••••'
                            : __('filament-registration::messages.secret_placeholder'))
                        ->helperText($secretHelper)
                        ->maxLength(500)
                        ->dehydrated(fn (?string $state) => filled($state)),

                    TextInput::make('captcha_recaptcha_min_score')
                        ->label(__('filament-registration::messages.min_score'))
                        ->helperText(__('filament-registration::messages.min_score_help'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1)
                        ->step(0.05)
                        ->visible(fn (callable $get) => $get('captcha_provider') === 'recaptcha_v3'),
                ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        $repo = app(SettingsRepository::class);
        $secretInDb = $repo->hasSecret('captcha_secret_key');

        return [
            Action::make('clear_secret')
                ->label(__('filament-registration::messages.clear_secret'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => $secretInDb)
                ->requiresConfirmation()
                ->modalDescription(__('filament-registration::messages.clear_secret_modal'))
                ->action(function () use ($repo) {
                    $repo->forget('captcha_secret_key');

                    Notification::make()
                        ->title(__('filament-registration::messages.secret_cleared_title'))
                        ->body(__('filament-registration::messages.secret_cleared_body'))
                        ->success()
                        ->send();
                }),

            Action::make('test')
                ->label(__('filament-registration::messages.test_verification'))
                ->color('gray')
                ->icon('heroicon-o-bolt')
                ->action(function () {
                    // Save first so the live config reflects what's in the form.
                    $this->save(notify: false);

                    $captcha = app(CaptchaManager::class)->resolve();

                    if (! $captcha->isEnabled()) {
                        Notification::make()
                            ->title(__('filament-registration::messages.captcha_not_enabled_title'))
                            ->body(__('filament-registration::messages.captcha_not_enabled_body'))
                            ->warning()
                            ->send();

                        return;
                    }

                    // Send a deliberately bogus token. A reachable, correctly-keyed
                    // provider should respond with a clean rejection (returns false).
                    // A misconfigured one will throw or return false too — rely on
                    // storage/logs/laravel.log for the underlying error.
                    $result = $captcha->verify('___test_invalid_token___', request()->ip());

                    if ($result === false) {
                        Notification::make()
                            ->title(__('filament-registration::messages.reachable_title'))
                            ->body(__('filament-registration::messages.reachable_body'))
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title(__('filament-registration::messages.unexpected_pass_title'))
                            ->body(__('filament-registration::messages.unexpected_pass_body'))
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function save(bool $notify = true): void
    {
        $data = $this->form->getState();

        $repo = app(SettingsRepository::class);

        $repo->setMany([
            'captcha_enabled' => (bool) ($data['captcha_enabled'] ?? false),
            'captcha_provider' => $data['captcha_provider'] ?? 'turnstile',
            'captcha_site_key' => (string) ($data['captcha_site_key'] ?? ''),
            'captcha_recaptcha_min_score' => (float) ($data['captcha_recaptcha_min_score'] ?? 0.5),
            // Empty / missing secret leaves the existing one untouched
            // (handled inside SettingsRepository::setMany).
            'captcha_secret_key' => $data['captcha_secret_key'] ?? null,
        ]);

        // Also nudge runtime config so the next request (and the test action
        // inside this same request) sees the new values immediately.
        config([
            'filament-registration.captcha.enabled' => (bool) ($data['captcha_enabled'] ?? false),
            'filament-registration.captcha.provider' => $data['captcha_provider'] ?? 'turnstile',
            'filament-registration.captcha.site_key' => (string) ($data['captcha_site_key'] ?? ''),
            'filament-registration.captcha.recaptcha_min_score' => (float) ($data['captcha_recaptcha_min_score'] ?? 0.5),
        ]);

        $dbSecret = $repo->get('captcha_secret_key');

        if (filled($dbSecret)) {
            config(['filament-registration.captcha.secret_key' => $dbSecret]);
        }

        // Force CaptchaProvider binding to be re-resolved on next call.
        app()->forgetInstance(CaptchaProvider::class);

        if ($notify) {
            Notification::make()
                ->title(__('filament-registration::messages.settings_saved'))
                ->success()
                ->send();
        }
    }
}
