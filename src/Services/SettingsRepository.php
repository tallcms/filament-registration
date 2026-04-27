<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Tallcms\FilamentRegistration\Models\RegistrationSetting;

/**
 * Wraps the tallcms_registration_settings key/value table.
 *
 * Stored values take precedence over config / env defaults — the service
 * provider merges them into runtime config at boot. Secret-class keys (see
 * SECRET_KEYS) are encrypted at rest via Laravel's app key and decrypted
 * only on read; the cache layer holds ciphertext, never plaintext.
 */
class SettingsRepository
{
    private const RAW_CACHE_KEY = 'filament-registration.settings.raw';

    private const CACHE_TTL = 3600;

    /** Settings the UI is allowed to write. */
    public const WRITABLE_KEYS = [
        'captcha_enabled',
        'captcha_provider',
        'captcha_site_key',
        'captcha_secret_key',
        'captcha_recaptcha_min_score',
    ];

    /** Keys treated as secrets — encrypted at rest, never displayed in UI. */
    public const SECRET_KEYS = [
        'captcha_secret_key',
    ];

    /**
     * Return all stored settings with secrets decrypted to plaintext.
     */
    public function all(): array
    {
        $raw = $this->loadRaw();

        foreach (self::SECRET_KEYS as $key) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }

            $cipher = $raw[$key];

            if (! is_string($cipher) || $cipher === '') {
                unset($raw[$key]);

                continue;
            }

            try {
                $raw[$key] = Crypt::decryptString($cipher);
            } catch (\Throwable $e) {
                unset($raw[$key]);
            }
        }

        return $raw;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    /**
     * Has a secret-class key been set in the DB? Used by the UI to render
     * "configured ✓ / ✗" hints without exposing the secret itself.
     */
    public function hasSecret(string $key): bool
    {
        if (! in_array($key, self::SECRET_KEYS, true)) {
            return false;
        }

        $raw = $this->loadRaw();

        return isset($raw[$key]) && is_string($raw[$key]) && $raw[$key] !== '';
    }

    public function setMany(array $values): void
    {
        if (! $this->tableExists()) {
            return;
        }

        foreach ($values as $key => $value) {
            if (! in_array($key, self::WRITABLE_KEYS, true)) {
                continue;
            }

            if (in_array($key, self::SECRET_KEYS, true)) {
                // Empty / null = "keep the existing secret untouched". To
                // explicitly clear a secret use forget($key) instead.
                if ($value === null || $value === '') {
                    continue;
                }

                $value = Crypt::encryptString((string) $value);
            }

            RegistrationSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->forgetCache();
    }

    /** Delete a stored setting. Clears the cache. */
    public function forget(string $key): void
    {
        if (! $this->tableExists()) {
            return;
        }

        RegistrationSetting::where('key', $key)->delete();

        $this->forgetCache();
    }

    public function forgetCache(): void
    {
        Cache::forget(self::RAW_CACHE_KEY);
    }

    private function loadRaw(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return Cache::remember(self::RAW_CACHE_KEY, self::CACHE_TTL, function () {
            return RegistrationSetting::query()->pluck('value', 'key')->toArray();
        });
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('tallcms_registration_settings');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
