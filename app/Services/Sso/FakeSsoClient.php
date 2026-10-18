<?php

namespace App\Services\Sso;

use Illuminate\Support\Str;

/**
 * Simulasi SSO untuk development (Laragon) dan test, TANPA menghubungi server kampus.
 *
 * Data diambil dari config('sso.fake_users'). Setiap pengguna punya 'response' yang
 * meniru respons Bridge /api/v2/profil, lalu diolah oleh SsoProfile::fromResponse()
 * yang SAMA dengan mode bridge. Jadi pemetaan field ikut teruji walau memakai mode fake.
 */
class FakeSsoClient implements SsoClient
{
    /**
     * @param  array  $config  isi config('sso')
     */
    public function __construct(private readonly array $config) {}

    public function authenticate(string $username, string $password): SsoProfile
    {
        $user = $this->config['fake_users'][$username] ?? null;

        // hash_equals: membandingkan string dengan waktu konstan (kebiasaan baik untuk password)
        if ($user === null || ! hash_equals((string) $user['password'], $password)) {
            throw SsoException::invalidCredentials();
        }

        return SsoProfile::fromResponse($user['response'], $username, $this->config['profile'], 'fake-'.Str::random(32));
    }

    public function logout(string $token): void
    {
        // Tidak ada sesi SSO sungguhan yang perlu diakhiri.
    }
}
