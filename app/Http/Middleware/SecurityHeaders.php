<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan HTTP di setiap respons (PRD 6.3). Pengaturan di config/security.php.
 *
 *   Content-Security-Policy  daftar sumber yang boleh dimuat browser (script, style, gambar, font, koneksi).
 *                            Kalau ada XSS yang lolos, script dari domain asing / script inline tetap ditolak browser.
 *   X-Frame-Options          halaman tidak boleh dibingkai (iframe) situs lain -> mencegah clickjacking
 *   X-Content-Type-Options   nosniff: browser tidak menebak-nebak jenis file (mis. lampiran dianggap HTML)
 *   Referrer-Policy          alamat lengkap halaman (bisa berisi nomor tiket) tidak dikirim ke situs lain
 *   Permissions-Policy       kamera, mikrofon, lokasi tidak dipakai -> dimatikan
 *   Strict-Transport-Security hanya lewat HTTPS: browser selalu memakai HTTPS untuk domain ini
 *
 * Saat development dengan "npm run dev", aset dimuat dari server Vite (mis. http://localhost:5173) dan
 * memakai websocket untuk auto-refresh. Alamat itu dibaca dari file public/hot dan ditambahkan ke CSP
 * otomatis, jadi CSP tidak perlu dimatikan di Laragon.
 */
class SecurityHeaders
{
    /** @param  string|null  $hotFile  file penanda "npm run dev" (bawaan Laravel: public/hot); bisa diganti di tes */
    public function __construct(private readonly ?string $hotFile = null) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security.enabled')) {
            return $response;
        }

        $headers = $response->headers;
        $headers->set('X-Frame-Options', config('security.frame_options'));
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', config('security.referrer_policy'));
        $headers->set('Permissions-Policy', config('security.permissions_policy'));

        if ($request->isSecure() && config('security.hsts_max_age') > 0) {
            $headers->set('Strict-Transport-Security', 'max-age='.config('security.hsts_max_age').'; includeSubDomains');
        }

        // Halaman error debug Laravel (APP_DEBUG=true, error 500) memakai script inline: jangan diblokir.
        $isDebugErrorPage = config('app.debug') && $response->getStatusCode() >= 500;

        if (config('security.csp.enabled') && ! $isDebugErrorPage) {
            $name = config('security.csp.report_only') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $headers->set($name, $this->policy());
        }

        return $response;
    }

    /** Susun nilai header Content-Security-Policy dari config. */
    public function policy(): string
    {
        $extra = config('security.csp.extra_sources', []);
        $dev = $this->viteDevServer();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", ...(config('security.csp.alpine_unsafe_eval') ? ["'unsafe-eval'"] : []), ...$dev['http'], ...$extra],
            'style-src' => ["'self'", ...(config('security.csp.inline_styles') ? ["'unsafe-inline'"] : []), ...$dev['http'], ...$extra],
            'img-src' => ["'self'", 'data:', 'blob:', ...$dev['http'], ...$extra],
            'font-src' => ["'self'", 'data:', ...$dev['http'], ...$extra],
            'connect-src' => ["'self'", ...$dev['http'], ...$dev['ws'], ...$extra],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', array_unique($sources)))
            ->implode('; ');
    }

    /**
     * Alamat server Vite saat "npm run dev" berjalan (isi file public/hot), mis. http://[::1]:5173.
     *
     * @return array{http: list<string>, ws: list<string>}
     */
    private function viteDevServer(): array
    {
        $hotFile = $this->hotFile ?? public_path('hot');

        if (! is_file($hotFile)) {
            return ['http' => [], 'ws' => []];
        }

        $url = rtrim(trim((string) file_get_contents($hotFile)), '/');
        if ($url === '') {
            return ['http' => [], 'ws' => []];
        }

        return [
            'http' => [$url],
            'ws' => [preg_replace('#^http#', 'ws', $url)],
        ];
    }
}
