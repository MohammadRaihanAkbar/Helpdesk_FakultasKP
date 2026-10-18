<?php

namespace App\Providers;

use App\Services\Sso\BridgeSsoClient;
use App\Services\Sso\FakeSsoClient;
use App\Services\Sso\SsoClient;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
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
    }
}
