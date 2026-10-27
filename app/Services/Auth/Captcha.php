<?php

namespace App\Services\Auth;

use Illuminate\Contracts\Session\Session;

/**
 * [M6] Captcha buatan sendiri untuk registrasi alumni — TANPA Google reCAPTCHA / Cloudflare Turnstile,
 * tanpa kunci API, tanpa internet (jalan di Laragon offline), tanpa ekstensi GD.
 *
 * Bentuknya soal penjumlahan, mis. "12 + 7 = ?", yang DIGAMBAR sebagai SVG:
 *   - setiap angka digambar sebagai garis (path) dari "font" mini di GLYPHS, BUKAN elemen <text>,
 *     jadi teks soalnya tidak bisa langsung dibaca dari kode SVG;
 *   - tiap angka diputar, digeser, dan diperbesar/diperkecil acak, titik-titiknya digoyang sedikit;
 *   - ditambah garis lengkung & titik-titik pengganggu (noise).
 *
 * Keamanan jawaban:
 *   - yang disimpan di session hanya HASH jawaban (HMAC dengan APP_KEY), bukan jawabannya;
 *   - SEKALI PAKAI: check() langsung menghapus soal dari session, benar maupun salah (tidak bisa ditebak berulang);
 *   - kedaluwarsa setelah config('helpdesk.captcha.ttl_minutes') menit (bawaan 10);
 *   - jawaban dirapikan dulu (spasi dibuang, huruf kecil), jadi " 19 " = "19".
 * Ditambah throttle pada route POST registrasi (6x/menit per IP) dan route gambar captcha.
 *
 * Batasan yang perlu diketahui: captcha sederhana seperti ini menahan bot/spam iseng, bukan penyerang yang
 * khusus menulis program pembaca gambar. Untuk helpdesk fakultas itu cukup; pengamanan utamanya tetap
 * verifikasi akun oleh Admin Fakultas (akun baru belum bisa login sebelum disetujui).
 */
class Captcha
{
    public const SESSION_PREFIX = 'captcha.';

    /**
     * "Font" mini: setiap karakter = beberapa garis patah di kotak 10 x 16 (x ke kanan, y ke bawah).
     * Satu string = satu garis: "x,y x,y x,y ...".
     */
    private const GLYPHS = [
        '0' => ['3,1 7,1 9,3 9,13 7,15 3,15 1,13 1,3 3,1', '8,3 2,13'],
        '1' => ['2,4 5,1 5,15', '2,15 8,15'],
        '2' => ['1,4 3,1 7,1 9,3 9,6 1,15 9,15'],
        '3' => ['1,2 3,1 7,1 9,3 9,6 7,8 4,8', '7,8 9,10 9,13 7,15 3,15 1,13'],
        '4' => ['7,15 7,1 1,10 9,10'],
        '5' => ['9,1 2,1 1,7 6,7 9,9 9,13 7,15 2,15 1,13'],
        '6' => ['8,1 4,1 1,5 1,13 3,15 7,15 9,13 9,9 7,7 3,7 1,9'],
        '7' => ['1,1 9,1 4,15', '3,8 7,8'],
        '8' => ['3,7 1,5 1,3 3,1 7,1 9,3 9,5 7,7 3,7 1,9 1,13 3,15 7,15 9,13 9,9 7,7'],
        '9' => ['9,8 3,8 1,6 1,3 3,1 7,1 9,3 9,11 6,15 2,15'],
        '+' => ['5,4 5,12', '1,8 9,8'],
        '=' => ['1,6 9,6', '1,10 9,10'],
        '?' => ['1,4 3,1 7,1 9,3 9,6 5,9 5,11', '5,14 5,15'],
    ];

    private const WIDTH = 240;

    private const HEIGHT = 72;

    public function __construct(private readonly Session $session) {}

    /** Buat soal baru untuk form $form, simpan hash jawabannya di session, kembalikan gambar SVG-nya. */
    public function generate(string $form): string
    {
        $a = random_int(2, 19);
        $b = random_int(1, 9);

        $this->session->put(self::SESSION_PREFIX.$form, self::payload((string) ($a + $b)));

        return $this->svg("{$a}+{$b}=?");
    }

    /**
     * Cocokkan jawaban. Soal SELALU dihapus dari session (sekali pakai), benar maupun salah.
     */
    public function check(string $form, mixed $answer): bool
    {
        $stored = $this->session->pull(self::SESSION_PREFIX.$form);

        if (! is_array($stored) || ! isset($stored['hash'], $stored['expires_at']) || ! is_scalar($answer)) {
            return false;
        }

        if (now()->getTimestamp() > (int) $stored['expires_at']) {
            return false;
        }

        return hash_equals($stored['hash'], self::hash((string) $answer));
    }

    /**
     * Isi session untuk satu soal. Publik supaya tes bisa menyiapkan soal dengan jawaban yang diketahui:
     *   $this->withSession(['captcha.alumni' => Captcha::payload('12')])
     *
     * @return array{hash: string, expires_at: int}
     */
    public static function payload(string $answer, ?int $ttlMinutes = null): array
    {
        $ttl = $ttlMinutes ?? (int) config('helpdesk.captcha.ttl_minutes', 10);

        return ['hash' => self::hash($answer), 'expires_at' => now()->addMinutes($ttl)->getTimestamp()];
    }

    private static function hash(string $answer): string
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', '', $answer));

        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }

    /** Gambar teks soal sebagai SVG berisi garis-garis (tanpa <text>). */
    public function svg(string $text): string
    {
        $parts = [];

        // Latar & garis lengkung pengganggu di BELAKANG angka.
        for ($i = 0; $i < 5; $i++) {
            $parts[] = $this->noiseCurve(['#bb8f32', '#0a5e3d', '#7d8a83'][$i % 3], 0.35 + $i * 0.05, 1.2);
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $step = (self::WIDTH - 24) / count($chars);
        foreach ($chars as $i => $char) {
            $scale = 2.3 + random_int(-15, 20) / 100;
            $x = 14 + $i * $step + random_int(-3, 3);
            $y = (self::HEIGHT - 16 * $scale) / 2 + random_int(-6, 6);
            $angle = random_int(-18, 18);
            $color = ['#13261f', '#0e423e', '#0a5e3d', '#7c5418'][random_int(0, 3)];

            $paths = collect(self::GLYPHS[$char] ?? [])->map(fn (string $line) => '<polyline points="'.$this->jitter($line).'"/>')->implode('');
            $parts[] = sprintf(
                '<g transform="translate(%.1f %.1f) rotate(%d %.1f %.1f) scale(%.2f)" fill="none" stroke="%s" stroke-width="%.2f" stroke-linecap="round" stroke-linejoin="round">%s</g>',
                $x, $y, $angle, 5 * $scale, 8 * $scale, $scale, $color, 2.4 / $scale + 0.35, $paths
            );
        }

        // Garis & titik pengganggu di DEPAN angka.
        for ($i = 0; $i < 3; $i++) {
            $parts[] = $this->noiseCurve(['#13261f', '#bb8f32', '#0a5e3d'][$i], 0.55, 1.6);
        }
        for ($i = 0; $i < 40; $i++) {
            $parts[] = sprintf('<circle cx="%d" cy="%d" r="%.1f" fill="%s" opacity="0.5"/>',
                random_int(0, self::WIDTH), random_int(0, self::HEIGHT), random_int(5, 16) / 10, ['#0a5e3d', '#bb8f32', '#52625a'][random_int(0, 2)]);
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" role="img" aria-label="Soal captcha">'
            .'<rect width="100%%" height="100%%" rx="10" fill="#f6f3ea"/>%3$s</svg>',
            self::WIDTH, self::HEIGHT, implode('', $parts)
        );
    }

    /** Geser setiap titik garis sedikit (±0,6) supaya bentuk angka tidak pernah persis sama. */
    private function jitter(string $line): string
    {
        return collect(explode(' ', $line))->map(function (string $point) {
            [$x, $y] = array_map('floatval', explode(',', $point));

            return sprintf('%.1f,%.1f', $x + random_int(-6, 6) / 10, $y + random_int(-6, 6) / 10);
        })->implode(' ');
    }

    private function noiseCurve(string $color, float $opacity, float $width): string
    {
        $h = self::HEIGHT;

        return sprintf(
            '<path d="M%d %d C %d %d, %d %d, %d %d" fill="none" stroke="%s" stroke-width="%.1f" opacity="%.2f"/>',
            random_int(-10, 30), random_int(0, $h), random_int(40, 110), random_int(-20, $h + 20),
            random_int(120, 200), random_int(-20, $h + 20), random_int(210, self::WIDTH + 10), random_int(0, $h),
            $color, $width, $opacity
        );
    }
}
