<?php

namespace App\Services\Sso;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Login SSO sungguhan lewat Bridge API v2 (docs/bridge-api-v2.postman.json).
 *
 * Alur: login (username+password) -> token -> profil (Bearer token) -> SsoProfile.
 * Memakai HTTP Client bawaan Laravel (Http facade), sehingga di test bisa dipalsukan
 * dengan Http::fake() tanpa menghubungi server kampus.
 */
class BridgeSsoClient implements SsoClient
{
    /**
     * @param  array  $config  isi config('sso')
     */
    public function __construct(private readonly array $config) {}

    public function authenticate(string $username, string $password): SsoProfile
    {
        // 1) Login -> token
        $login = $this->send(fn () => $this->http()
            ->asForm() // Postman: body x-www-form-urlencoded
            ->post($this->config['endpoints']['login'], [
                'username' => $username,
                'password' => $password,
            ]));

        if (in_array($login->status(), [401, 422], true)) {
            throw SsoException::invalidCredentials();
        }
        if ($login->serverError()) {
            throw SsoException::unreachable();
        }

        // Sama dengan script Postman: token ada di data.token atau token
        $token = $login->json('data.token') ?? $login->json('token');
        if (! $login->successful() || ! is_string($token) || $token === '') {
            throw SsoException::invalidCredentials();
        }

        // 2) Profil
        $profile = $this->send(fn () => $this->http()->withToken($token)->get($this->config['endpoints']['profile']));

        if ($profile->failed()) {
            Log::warning('SSO Bridge: gagal mengambil profil', ['status' => $profile->status()]);
            throw $profile->serverError() ? SsoException::unreachable() : SsoException::invalidProfile('HTTP '.$profile->status());
        }

        return SsoProfile::fromResponse((array) $profile->json(), $username, $this->config['profile'], $token);
    }

    public function logout(string $token): void
    {
        try {
            $this->http()->withToken($token)->post($this->config['endpoints']['logout']);
        } catch (Throwable $e) {
            // Best effort: sesi aplikasi tetap diakhiri walau Bridge gagal dihubungi.
            Log::info('SSO Bridge: logout gagal (diabaikan)', ['error' => $e->getMessage()]);
        }
    }

    /** Request dasar: base URL, timeout, dan header Accept: application/json. */
    private function http(): PendingRequest
    {
        return Http::baseUrl($this->config['bridge_url'])
            ->timeout($this->config['timeout'] ?? 10)
            ->acceptJson();
    }

    /**
     * Jalankan request; gagal koneksi (timeout, DNS, server mati) diubah menjadi pesan yang ramah.
     *
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        if (empty($this->config['bridge_url'])) {
            Log::error('SSO Bridge: SSO_BRIDGE_URL belum diisi di .env');
            throw SsoException::unreachable();
        }

        try {
            return $request();
        } catch (ConnectionException $e) {
            Log::warning('SSO Bridge: tidak dapat terhubung', ['error' => $e->getMessage()]);
            throw SsoException::unreachable();
        }
    }
}
