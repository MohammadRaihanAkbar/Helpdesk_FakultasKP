<?php

/*
|--------------------------------------------------------------------------
| Pengaturan fitur Helpdesk (M6)
|--------------------------------------------------------------------------
|
| Angka-angka aturan bisnis yang mungkin ingin diubah tanpa menyentuh kode PHP.
| Semuanya bisa ditimpa dari .env. Penjelasan lengkap: docs/progres/M6-*.md.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Email notifikasi (F-NTF-01..04, 06, 07 + pengingat rating + verifikasi alumni)
    |--------------------------------------------------------------------------
    |
    | Email dikirim lewat mailer bawaan Laravel (config/mail.php, MAIL_* di .env) — SMTP Gmail
    | helpdeskfakultas@gmail.com, tanpa layanan pihak ketiga. Penerima = users.email.
    | Akun tanpa email asli (email pengganti {nim}@SSO_EMAIL_FALLBACK_DOMAIN) hanya mendapat notifikasi in-app
    | (lihat User::canReceiveEmail()).
    |
    | false -> hanya notifikasi in-app (seperti M5). Berguna kalau akun Gmail sedang bermasalah.
    */
    'email_notifications' => (bool) env('MAIL_NOTIFICATIONS', true),

    /*
    | Percobaan ulang email yang gagal terkirim (PRD 6.4: 3x dengan exponential backoff).
    | backoff = jeda (detik) sebelum percobaan ke-2 dan ke-3.
    */
    'mail_tries' => 3,
    'mail_backoff' => [60, 300],

    /*
    |--------------------------------------------------------------------------
    | Pengingat rating (php artisan helpdesk:pengingat-rating, dijadwalkan tiap jam)
    |--------------------------------------------------------------------------
    |
    | Tiket Selesai yang belum dirating mendapat pengingat (email + in-app):
    |   pengingat ke-1  : first_after_hours jam setelah tiket selesai (resolved_at)
    |   pengingat ke-2+ : interval_hours jam setelah pengingat sebelumnya
    |   paling banyak   : max_reminders kali, berhenti begitu pelapor memberi rating
    |   max_age_days    : tiket yang selesai lebih lama dari ini tidak diingatkan lagi
    |                     (supaya tiket lama tidak "dibanjiri" email saat fitur ini pertama kali dinyalakan)
    | Tiket Ditolak tidak pernah diingatkan (tidak bisa dirating).
    */
    'rating_reminder' => [
        'first_after_hours' => (int) env('RATING_REMINDER_FIRST_AFTER_HOURS', 24),
        'interval_hours' => (int) env('RATING_REMINDER_INTERVAL_HOURS', 48),
        'max_reminders' => (int) env('RATING_REMINDER_MAX', 2),
        'max_age_days' => (int) env('RATING_REMINDER_MAX_AGE_DAYS', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | Captcha registrasi alumni (buatan sendiri, tanpa Google reCAPTCHA / layanan luar)
    |--------------------------------------------------------------------------
    |
    | Soal penjumlahan yang digambar sebagai SVG (lihat App\Services\Auth\Captcha). Jawaban disimpan
    | ter-hash di session, sekali pakai, kedaluwarsa setelah ttl_minutes menit.
    |
    | enabled = false HANYA untuk tes otomatis (phpunit.xml). Jangan dimatikan di Laragon/server.
    */
    'captcha' => [
        'enabled' => (bool) env('CAPTCHA_ENABLED', true),
        'ttl_minutes' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Halaman depan (landing page) publik
    |--------------------------------------------------------------------------
    |
    | Statistik "Tren Tiket" di halaman depan sama untuk semua pengunjung, jadi disimpan di cache
    | (bawaan 15 menit) supaya halaman publik tetap ringan walau banyak yang membuka.
    | contact_email = alamat kontak di footer halaman depan.
    */
    'landing' => [
        'cache_seconds' => (int) env('LANDING_CACHE_SECONDS', 900),
        'contact_email' => env('HELPDESK_CONTACT_EMAIL', 'helpdeskfakultas@gmail.com'),
    ],
];
