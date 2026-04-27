<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Tests\Unit;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tallcms\FilamentRegistration\Services\SettingsRepository;
use Tallcms\FilamentRegistration\Tests\TestCase;

class SettingsRepositoryTest extends TestCase
{
    public function test_secrets_are_encrypted_at_rest_and_decrypted_on_read(): void
    {
        $repo = new SettingsRepository;

        $repo->setMany([
            'captcha_secret_key' => 'plain-secret-value',
        ]);

        // The DB row should contain ciphertext, not the plain value.
        $rawValue = DB::table('tallcms_registration_settings')
            ->where('key', 'captcha_secret_key')
            ->value('value');

        $rawDecoded = json_decode($rawValue, true);
        $this->assertNotSame('plain-secret-value', $rawDecoded);
        $this->assertSame('plain-secret-value', Crypt::decryptString($rawDecoded));

        // Reading via the repository decrypts.
        $this->assertSame('plain-secret-value', $repo->get('captcha_secret_key'));
    }

    public function test_set_many_only_writes_writable_keys(): void
    {
        $repo = new SettingsRepository;

        $repo->setMany([
            'captcha_enabled' => true,
            'arbitrary_key' => 'should not be written',
        ]);

        $this->assertSame(true, $repo->get('captcha_enabled'));
        $this->assertNull($repo->get('arbitrary_key'));
    }

    public function test_empty_secret_value_does_not_overwrite_existing_secret(): void
    {
        $repo = new SettingsRepository;

        $repo->setMany(['captcha_secret_key' => 'first-value']);
        $repo->setMany(['captcha_secret_key' => '']);

        $this->assertSame('first-value', $repo->get('captcha_secret_key'));
    }

    public function test_forget_removes_a_key(): void
    {
        $repo = new SettingsRepository;

        $repo->setMany(['captcha_secret_key' => 'value']);
        $this->assertTrue($repo->hasSecret('captcha_secret_key'));

        $repo->forget('captcha_secret_key');
        $this->assertFalse($repo->hasSecret('captcha_secret_key'));
        $this->assertNull($repo->get('captcha_secret_key'));
    }

    public function test_has_secret_only_reports_secret_keys(): void
    {
        $repo = new SettingsRepository;

        $repo->setMany([
            'captcha_secret_key' => 'value',
            'captcha_enabled' => true,
        ]);

        $this->assertTrue($repo->hasSecret('captcha_secret_key'));
        $this->assertFalse($repo->hasSecret('captcha_enabled'));
        $this->assertFalse($repo->hasSecret('arbitrary_key'));
    }
}
