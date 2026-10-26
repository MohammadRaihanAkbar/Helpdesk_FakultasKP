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
        $user = $this->findUser($username);

        // hash_equals: membandingkan string dengan waktu konstan (kebiasaan baik untuk password)
        if ($user === null || ! hash_equals((string) $user['password'], $password)) {
            throw SsoException::invalidCredentials();
        }

        return SsoProfile::fromResponse($user['response'], $username, $this->config['profile'], 'fake-'.Str::random(32));
    }

    /**
     * Cari pengguna simulasi berdasarkan username yang diketik.
     *
     * 1. Kunci array fake_users (NIM / NIP / NIDN yang ditulis sebagai kunci).
     * 2. Kalau tidak ada: cocokkan dengan field NIP atau NIDN di isi profilnya. Dosen diidentifikasi dengan NIP
     *    (keputusan M5), tetapi NIDN tetap diterima supaya "Dr. Budi Dosen" bisa login dengan NIP
     *    198804122015031001 maupun NIDN 0412038801. (Apakah Bridge kampus menerima NIDN belum pasti, lihat docs/SSO.md.)
     */
    private function findUser(string $username): ?array
    {
        $users = $this->config['fake_users'] ?? [];

        if (isset($users[$username])) {
            return $users[$username];
        }

        $root = $this->config['profile']['root'] ?? null;
        $fields = $this->config['profile']['fields'] ?? [];
        $paths = array_merge((array) ($fields['nidn'] ?? []), (array) ($fields['nip'] ?? []));

        foreach ($users as $user) {
            $data = $root ? data_get($user['response'], $root) : $user['response'];

            foreach ($paths as $path) {
                $value = data_get($data, $path);
                if (is_scalar($value) && (string) $value === $username) {
                    return $user;
                }
            }
        }

        return null;
    }

    public function logout(string $token): void
    {
        // Tidak ada sesi SSO sungguhan yang perlu diakhiri.
    }
}
