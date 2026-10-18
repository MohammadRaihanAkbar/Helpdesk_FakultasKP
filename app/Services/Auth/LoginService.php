<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Sso\SsoClient;
use App\Services\Sso\SsoException;
use App\Services\Sso\SsoUserSynchronizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Logika login dengan SATU field "login" (rencana teknis 3.1):
 *
 *   berisi "@"  -> LOGIN LOKAL (email + password di tabel users)
 *                  untuk Staff, Admin Fakultas, Super Admin, dan Alumni lulus <= 2021
 *   selain itu  -> LOGIN SSO (NIM/NIDN + password SSO kampus)
 *                  untuk Mahasiswa, Dosen, dan Alumni lulus >= 2022
 *
 * Kenapa dipisah dari controller? Supaya controller tetap tipis (hanya terima request &
 * redirect), dan alur login bisa dibaca utuh di satu file.
 *
 * Kalau gagal, method ini melempar ValidationException: Laravel otomatis kembali ke form
 * login dengan pesan error di bawah field "login" (dan input lama tetap terisi).
 */
class LoginService
{
    /** Session key untuk menyimpan token Bridge (dipakai saat logout SSO). */
    public const SSO_TOKEN_SESSION_KEY = 'sso_token';

    public function __construct(
        private readonly SsoClient $sso,
        private readonly SsoUserSynchronizer $synchronizer,
    ) {}

    public static function isLocalLogin(string $login): bool
    {
        return str_contains($login, '@');
    }

    /**
     * @throws ValidationException
     */
    public function attempt(string $login, string $password, bool $remember): User
    {
        $user = self::isLocalLogin($login)
            ? $this->authenticateLocal($login, $password)
            : $this->authenticateSso($login, $password);

        // Berlaku untuk kedua jalur: akun yang dinonaktifkan Admin tidak boleh masuk.
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi Admin Fakultas.',
            ]);
        }

        // Simpan user ke session. $remember = true -> cookie "ingat saya" (7 hari, config/auth.php).
        Auth::login($user, $remember);

        return $user;
    }

    /** Email + password dicocokkan dengan hash bcrypt di kolom users.password. */
    private function authenticateLocal(string $email, string $password): User
    {
        $user = User::where('email', mb_strtolower($email))->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['login' => trans('auth.failed')]);
        }

        return $user;
    }

    /** NIM/NIDN dikirim ke SSO; profilnya disinkronkan ke tabel users. */
    private function authenticateSso(string $username, string $password): User
    {
        try {
            $profile = $this->sso->authenticate($username, $password);
        } catch (SsoException $e) {
            throw ValidationException::withMessages(['login' => $e->getMessage()]);
        }

        $user = $this->synchronizer->sync($profile);

        if ($profile->token) {
            session()->put(self::SSO_TOKEN_SESSION_KEY, $profile->token);
        }

        return $user;
    }
}
