<?php

declare(strict_types=1);

return [
    'navigation_label' => 'Registration',
    'navigation_group' => 'Settings',
    'title' => 'Registration & CAPTCHA',
    'save_settings' => 'Save Settings',

    'section_captcha' => 'CAPTCHA',
    'section_captcha_description' => 'Bot protection on the public registration form. Leave disabled to fall back to honeypot + rate limiting only.',

    'enable_captcha' => 'Enable CAPTCHA',
    'enable_captcha_help' => 'When off, the registration form skips CAPTCHA verification entirely.',
    'provider' => 'Provider',
    'provider_turnstile' => 'Cloudflare Turnstile',
    'provider_recaptcha_v3' => 'Google reCAPTCHA v3',
    'provider_help' => 'Cloudflare Turnstile is privacy-friendly and free. Get keys at <a href=":turnstile_url" target="_blank" class="underline">Cloudflare Turnstile</a>. reCAPTCHA v3 keys come from the <a href=":recaptcha_url" target="_blank" class="underline">reCAPTCHA admin console</a>.',

    'site_key' => 'Site key',
    'site_key_help' => 'Public key embedded in the form. Safe to put in source control.',

    'secret_status' => 'Secret key status',
    'secret_configured' => '✓ Configured',
    'secret_not_set' => '✗ Not set — registration will fall back to no CAPTCHA',

    'secret_key' => 'Secret key',
    'replace_secret_key' => 'Replace secret key',
    'secret_placeholder' => 'Paste your provider secret key',
    'secret_help_in_db' => 'A secret is already saved (encrypted in the database). Leave this blank to keep it, or paste a new one to replace it.',
    'secret_help_in_env' => 'A secret is set in your server environment. Paste a value here to override it from the database, or leave blank to keep using the environment value.',
    'secret_help_default' => 'Paste the secret key from your CAPTCHA provider. It will be encrypted before being saved.',

    'min_score' => 'Minimum score (reCAPTCHA v3 only)',
    'min_score_help' => 'Tokens scoring below this threshold are rejected. Range 0.0 (lenient) – 1.0 (strict). Default 0.5.',

    'clear_secret' => 'Clear saved secret',
    'clear_secret_modal' => 'This deletes the encrypted secret from the database. CAPTCHA verification will fall back to the value in FILAMENT_REGISTRATION_CAPTCHA_SECRET_KEY (if set), or disable itself if no env value exists.',
    'secret_cleared_title' => 'Saved secret cleared',
    'secret_cleared_body' => 'Now using the environment value (if any).',

    'test_verification' => 'Test verification',
    'captcha_not_enabled_title' => 'CAPTCHA is not enabled',
    'captcha_not_enabled_body' => 'Enable it and configure both keys, then try again.',
    'reachable_title' => 'Reachable',
    'reachable_body' => 'Provider responded and rejected a deliberately bogus token, as expected. Live submissions with valid tokens should pass.',
    'unexpected_pass_title' => 'Unexpected pass',
    'unexpected_pass_body' => 'A bogus token was accepted. Check your secret key and provider configuration.',

    'settings_saved' => 'Registration settings saved',

    'verification' => 'Verification',
    'bot_check_failed' => 'Bot check failed. Please try again.',
    'too_many_attempts' => 'Too many attempts. Please wait a minute and try again.',
    'captcha_failed' => 'Captcha verification failed. Please try again.',
];
