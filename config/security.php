<?php

/*
|--------------------------------------------------------------------------
| Header keamanan HTTP (M5, PRD 6.3 "XSS Prevention: Content Security Policy header")
|--------------------------------------------------------------------------
|
| Dipasang oleh App\Http\Middleware\SecurityHeaders di SEMUA respons (bootstrap/app.php).
| Penjelasan tiap header ada di docs/progres/M5-super-admin-notifikasi-keamanan.md.
|
| Kalau suatu saat ada sumber luar baru (mis. gambar dari CDN), tambahkan di CSP_EXTRA_SOURCES (.env),
| dipisah spasi, contoh: CSP_EXTRA_SOURCES="https://cdn.uinssc.ac.id"
|
*/

return [

    /* Matikan SEMUA header keamanan tambahan (hanya untuk mencari masalah, jangan di production). */
    'enabled' => (bool) env('SECURITY_HEADERS', true),

    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', true),

        /*
        | true -> header Content-Security-Policy-Report-Only: browser hanya MELAPORKAN pelanggaran di Console,
        | tidak memblokir. Berguna untuk mencoba kebijakan baru tanpa merusak halaman.
        */
        'report_only' => (bool) env('CSP_REPORT_ONLY', false),

        /*
        | Alpine.js versi standar mengevaluasi atribut x-data/@click dengan new Function(), jadi butuh 'unsafe-eval'.
        | (Alternatifnya build CSP Alpine, @alpinejs/csp, yang tidak mendukung ekspresi bebas di atribut.)
        */
        'alpine_unsafe_eval' => true,

        /*
        | 'unsafe-inline' untuk style: @fonts menulis <style>@font-face</style> dan beberapa view memakai
        | style="width: ..%" (grafik batang). Script inline TIDAK diizinkan (tidak ada yang memakainya).
        */
        'inline_styles' => true,

        /* Sumber tambahan untuk semua jenis (script, style, img, font, connect). */
        'extra_sources' => array_values(array_filter(explode(' ', (string) env('CSP_EXTRA_SOURCES', '')))),
    ],

    'frame_options' => 'SAMEORIGIN',
    'referrer_policy' => 'strict-origin-when-cross-origin',
    'permissions_policy' => 'camera=(), microphone=(), geolocation=(), payment=()',

    /* HSTS hanya dikirim untuk request HTTPS (production). 0 = tidak dikirim. */
    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
];
