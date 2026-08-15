<?php

declare(strict_types=1);

return [
    'navigation_label' => 'Registrierung',
    'navigation_group' => 'Einstellungen',
    'title' => 'Registrierung & CAPTCHA',
    'save_settings' => 'Einstellungen speichern',

    'section_captcha' => 'CAPTCHA',
    'section_captcha_description' => 'Bot-Schutz auf dem öffentlichen Registrierungsformular. Deaktiviert bleiben nur Honeypot und Rate-Limiting.',

    'enable_captcha' => 'CAPTCHA aktivieren',
    'enable_captcha_help' => 'Wenn aus, überspringt das Registrierungsformular die CAPTCHA-Prüfung vollständig.',
    'provider' => 'Anbieter',
    'provider_turnstile' => 'Cloudflare Turnstile',
    'provider_recaptcha_v3' => 'Google reCAPTCHA v3',
    'provider_help' => 'Cloudflare Turnstile ist datenschutzfreundlich und kostenlos. Schlüssel unter <a href=":turnstile_url" target="_blank" class="underline">Cloudflare Turnstile</a>. reCAPTCHA-v3-Schlüssel aus der <a href=":recaptcha_url" target="_blank" class="underline">reCAPTCHA-Admin-Konsole</a>.',

    'site_key' => 'Site-Key',
    'site_key_help' => 'Öffentlicher Schlüssel im Formular. Kann in die Versionskontrolle.',

    'secret_status' => 'Secret-Key-Status',
    'secret_configured' => '✓ Konfiguriert',
    'secret_not_set' => '✗ Nicht gesetzt — Registrierung fällt auf kein CAPTCHA zurück',

    'secret_key' => 'Secret-Key',
    'replace_secret_key' => 'Secret-Key ersetzen',
    'secret_placeholder' => 'Secret-Key des Anbieters einfügen',
    'secret_help_in_db' => 'Ein Secret ist bereits gespeichert (verschlüsselt in der Datenbank). Leer lassen zum Behalten, oder ein neues einfügen zum Ersetzen.',
    'secret_help_in_env' => 'Ein Secret ist in der Server-Umgebung gesetzt. Hier einen Wert einfügen, um ihn aus der Datenbank zu überschreiben, oder leer lassen, um den Umgebungs-Wert weiter zu nutzen.',
    'secret_help_default' => 'Secret-Key Ihres CAPTCHA-Anbieters einfügen. Er wird vor dem Speichern verschlüsselt.',

    'min_score' => 'Mindest-Score (nur reCAPTCHA v3)',
    'min_score_help' => 'Tokens unter diesem Schwellenwert werden abgelehnt. Bereich 0.0 (locker) – 1.0 (streng). Standard 0.5.',

    'clear_secret' => 'Gespeichertes Secret löschen',
    'clear_secret_modal' => 'Löscht das verschlüsselte Secret aus der Datenbank. CAPTCHA fällt auf FILAMENT_REGISTRATION_CAPTCHA_SECRET_KEY zurück (falls gesetzt), oder deaktiviert sich ohne Env-Wert.',
    'secret_cleared_title' => 'Gespeichertes Secret gelöscht',
    'secret_cleared_body' => 'Es wird jetzt der Umgebungs-Wert verwendet (falls vorhanden).',

    'test_verification' => 'Verifizierung testen',
    'captcha_not_enabled_title' => 'CAPTCHA ist nicht aktiviert',
    'captcha_not_enabled_body' => 'Aktivieren Sie es und konfigurieren Sie beide Schlüssel, dann erneut versuchen.',
    'reachable_title' => 'Erreichbar',
    'reachable_body' => 'Der Anbieter hat geantwortet und einen absichtlich ungültigen Token abgelehnt — wie erwartet. Echte Einsendungen mit gültigen Tokens sollten durchgehen.',
    'unexpected_pass_title' => 'Unerwarteter Erfolg',
    'unexpected_pass_body' => 'Ein ungültiger Token wurde akzeptiert. Prüfen Sie Secret-Key und Anbieterkonfiguration.',

    'settings_saved' => 'Registrierungseinstellungen gespeichert',

    'verification' => 'Verifizierung',
    'bot_check_failed' => 'Bot-Prüfung fehlgeschlagen. Bitte erneut versuchen.',
    'too_many_attempts' => 'Zu viele Versuche. Bitte eine Minute warten und erneut versuchen.',
    'captcha_failed' => 'CAPTCHA-Prüfung fehlgeschlagen. Bitte erneut versuchen.',
];
