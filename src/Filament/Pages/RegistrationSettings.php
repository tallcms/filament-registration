<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
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
use Illuminate\Support\HtmlString;
use Tallcms\FilamentRegistration\Captcha\CaptchaManager;
use Tallcms\FilamentRegistration\Captcha\Contracts\CaptchaProvider;
use Tallcms\FilamentRegistration\Services\SettingsRepository;

/**
 * Filament admin page for captcha configuration.
 *
 * Gated through Filament Shield's `HasPageShield` trait — auto-creates a
 * `View:RegistrationSettings` permission that hosts can toggle in the
 * Shield role UI. Falls back to a public page if Shield isn't installed
 * (the trait handles that case gracefully).
 */
class RegistrationSettings extends Page implements HasForms
{
    use HasPageShield, InteractsWithForms;

    protected string $view = 'filament-registration::filament.pages.registration-settings';

    protected static ?string $navigationLabel = 'Registration';

    protected static ?string $title = 'Registration & CAPTCHA';

    public ?array $data = [];

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
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
            $secretInDb => 'A secret is already saved (encrypted in the database). Leave this blank to keep it, or paste a new one to replace it.',
            $secretFromOutsideDb => 'A secret is set in your server environment. Paste a value here to override it from the database, or leave blank to keep using the environment value.',
            default => 'Paste the secret key from your CAPTCHA provider. It will be encrypted before being saved.',
        };

        return [
            Section::make('CAPTCHA')
                ->description('Bot protection on the public registration form. Leave disabled to fall back to honeypot + rate limiting only.')
                ->schema([
                    Toggle::make('captcha_enabled')
                        ->label('Enable CAPTCHA')
                        ->helperText('When off, the registration form skips CAPTCHA verification entirely.'),

                    Select::make('captcha_provider')
                        ->label('Provider')
                        ->options([
                            'turnstile' => 'Cloudflare Turnstile',
                            'recaptcha_v3' => 'Google reCAPTCHA v3',
                        ])
                        ->required()
                        ->live()
                        ->helperText(new HtmlString(
                            'Cloudflare Turnstile is privacy-friendly and free. Get keys at '
                            .'<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" class="underline">Cloudflare Turnstile</a>. '
                            .'reCAPTCHA v3 keys come from the '
                            .'<a href="https://www.google.com/recaptcha/admin" target="_blank" class="underline">reCAPTCHA admin console</a>.'
                        )),

                    TextInput::make('captcha_site_key')
                        ->label('Site key')
                        ->helperText('Public key embedded in the form. Safe to put in source control.')
                        ->maxLength(255),

                    Placeholder::make('captcha_secret_status')
                        ->label('Secret key status')
                        ->content(fn () => new HtmlString(
                            $secretConfigured
                                ? '<span class="text-success font-medium">✓ Configured</span>'
                                : '<span class="text-warning font-medium">✗ Not set — registration will fall back to no CAPTCHA</span>'
                        )),

                    TextInput::make('captcha_secret_key')
                        ->label($secretConfigured ? 'Replace secret key' : 'Secret key')
                        ->password()
                        ->revealable()
                        ->placeholder($secretConfigured ? '••••••••' : 'Paste your provider secret key')
                        ->helperText($secretHelper)
                        ->maxLength(500)
                        ->dehydrated(fn (?string $state) => filled($state)),

                    TextInput::make('captcha_recaptcha_min_score')
                        ->label('Minimum score (reCAPTCHA v3 only)')
                        ->helperText('Tokens scoring below this threshold are rejected. Range 0.0 (lenient) – 1.0 (strict). Default 0.5.')
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
                ->label('Clear saved secret')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => $secretInDb)
                ->requiresConfirmation()
                ->modalDescription('This deletes the encrypted secret from the database. CAPTCHA verification will fall back to the value in FILAMENT_REGISTRATION_CAPTCHA_SECRET_KEY (if set), or disable itself if no env value exists.')
                ->action(function () use ($repo) {
                    $repo->forget('captcha_secret_key');

                    Notification::make()
                        ->title('Saved secret cleared')
                        ->body('Now using the environment value (if any).')
                        ->success()
                        ->send();
                }),

            Action::make('test')
                ->label('Test verification')
                ->color('gray')
                ->icon('heroicon-o-bolt')
                ->action(function () {
                    // Save first so the live config reflects what's in the form.
                    $this->save(notify: false);

                    $captcha = app(CaptchaManager::class)->resolve();

                    if (! $captcha->isEnabled()) {
                        Notification::make()
                            ->title('CAPTCHA is not enabled')
                            ->body('Enable it and configure both keys, then try again.')
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
                            ->title('Reachable')
                            ->body('Provider responded and rejected a deliberately bogus token, as expected. Live submissions with valid tokens should pass.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Unexpected pass')
                            ->body('A bogus token was accepted. Check your secret key and provider configuration.')
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
                ->title('Registration settings saved')
                ->success()
                ->send();
        }
    }
}
