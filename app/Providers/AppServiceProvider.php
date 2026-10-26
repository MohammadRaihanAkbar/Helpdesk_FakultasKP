<?php

namespace App\Providers;

use App\Services\Sso\BridgeSsoClient;
use App\Services\Sso\FakeSsoClient;
use App\Services\Sso\SsoClient;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /** Batas umum request per menit untuk user yang login (PRD 6.3). */
    public const AUTHENTICATED_PER_MINUTE = 60;

    /**
     * Register any application services.
     *
     * Di sini kita "mengajari" service container Laravel: kalau ada class yang meminta
     * SsoClient (interface) di constructor/method-nya, berikan implementasi sesuai SSO_DRIVER.
     */
    public function register(): void
    {
        $this->app->bind(SsoClient::class, function () {
            $config = config('sso');

            return match ($config['driver']) {
                'bridge' => new BridgeSsoClient($config),
                'fake' => new FakeSsoClient($config),
                default => throw new InvalidArgumentException("SSO_DRIVER tidak dikenal: {$config['driver']} (pilih fake atau bridge)"),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Nama hari/bulan & diffForHumans() dalam Bahasa Indonesia ("3 jam yang lalu").
        Carbon::setLocale(config('app.locale'));

        // Aturan password default untuk Password::defaults() (registrasi alumni, akun staff di M4).
        Password::defaults(fn () => Password::min(8));

        // Tampilan navigasi halaman sendiri (resources/views/pagination.blade.php) yang memakai warna tema.
        Paginator::defaultView('pagination');

        $this->configureRateLimiting();
    }

    /**
     * Batas jumlah request per IP untuk route tamu (dipakai lewat middleware 'throttle:login', dst.).
     *
     * Kenapa limiter BERNAMA, bukan 'throttle:20,1'? Throttle tanpa nama menghitung per IP untuk
     * SEMUA route sekaligus, jadi hitungan login dan registrasi tercampur: setelah beberapa kali
     * login, form registrasi alumni ikut ditolak "429 Too Many Requests". Limiter bernama punya
     * hitungan sendiri-sendiri.
     *
     * Ini lapisan kedua. Lapisan utama anti tebak password tetap 5x gagal/menit per login+IP
     * (LoginRequest). Batas login per IP dibuat longgar (60/menit, PRD 6.3) karena satu jaringan
     * Wi-Fi kampus bisa dipakai banyak mahasiswa dengan IP publik yang sama.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('alumni-registration', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));

        // Kirim tiket & balasan thread: 30/menit per AKUN (bukan per IP, karena Wi-Fi kampus berbagi IP).
        // Cukup longgar untuk pemakaian wajar, tapi menahan skrip yang membanjiri antrean.
        // Ping Admin (US-14): PRD tidak membatasi jumlah Ping, jadi ini hanya penahan skrip/klik beruntun
        // (20/menit per akun). Klik ganda pada tiket yang sama ditahan PingService (jeda 30 detik).
        RateLimiter::for('pings', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('tickets', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        // [M5] Batas UMUM semua halaman web untuk user yang login (PRD 6.3: 60 request/menit), per akun.
        // Dipasang di grup web (bootstrap/app.php). Tamu tidak dibatasi di sini: halaman login punya
        // limiter sendiri, dan membatasi tamu per IP akan mengganggu satu Wi-Fi kampus yang berbagi IP.
        RateLimiter::for('authenticated', fn (Request $request) => $request->user()
            ? Limit::perMinute(self::AUTHENTICATED_PER_MINUTE)->by('user:'.$request->user()->id)
            : Limit::none());
    }
}
