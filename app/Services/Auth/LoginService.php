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
 *   selain itu  -> (1) cek dulu: apakah ini NIM alumni hidden link (lulus <= 2021)?
 *                      Jika ya dan password cocok -> LOGIN LOKAL dengan NIM.
 *                  (2) kalau tidak -> LOGIN SSO (NIM/NIDN/NIP + password SSO kampus)
 *                      untuk Mahasiswa, Dosen, dan Alumni lulus >= 2022
 *
 * Kenapa langkah (1) perlu? Alumni lulus <= 2021 TIDAK ADA di SSO kampus. Dulu semua input
 * tanpa "@" langsung dikirim ke SSO, sehingga alumni yang mengetik NIM selalu ditolak
 * "NIM/NIDN atau password salah" padahal akunnya sudah tersimpan (bug M1, lihat
 * docs/progres/M1-perbaikan-bug.md).
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

    /**
     * Pesan jika login dengan NIM/NIDN/NIP gagal. Sengaja menyebut kedua kemungkinan password
     * (SSO atau registrasi alumni) tanpa memberi tahu akun mana yang ada, supaya orang lain
     * tidak bisa menebak NIM siapa saja yang terdaftar.
     */
    public const IDENTITY_FAILED_MESSAGE = 'NIM/NIDN/NIP atau password salah. Gunakan password SSO kampus; alumni lulusan 2021 ke bawah memakai password yang dibuat saat registrasi.';

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
            : ($this->authenticateHiddenLinkAlumni($login, $password) ?? $this->authenticateSso($login, $password));

        // Berlaku untuk semua jalur: akun yang dinonaktifkan Admin tidak boleh masuk.
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

    /**
     * Alumni hidden link (lulus <= 2021) boleh login memakai NIM + password registrasinya.
     *
     * Hanya akun yang memenuhi SEMUA syarat ini yang dicek password lokalnya (scope User::hiddenLinkAlumni()):
     *   - user_type = alumni
     *   - graduation_year terisi dan <= 2021 (alumni >= 2022 wajib lewat SSO)
     *
     * Akun hasil sinkron SSO (mahasiswa, dosen, alumni >= 2022) tidak pernah lolos syarat di atas,
     * jadi tetap wajib login lewat SSO. Seandainya pun lolos, password lokalnya adalah string acak
     * 40 karakter (SsoUserSynchronizer) yang tidak diketahui siapa pun.
     *
     * Mengembalikan null (bukan error) jika tidak cocok, supaya login dilanjutkan ke SSO.
     */
    private function authenticateHiddenLinkAlumni(string $nim, string $password): ?User
    {
        $user = User::hiddenLinkAlumni()->where('nim', $nim)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    /** NIM/NIDN/NIP dikirim ke SSO; profilnya disinkronkan ke tabel users. */
    private function authenticateSso(string $username, string $password): User
    {
        try {
            $profile = $this->sso->authenticate($username, $password);
        } catch (SsoException $e) {
            $message = $e->errorCode === SsoException::INVALID_CREDENTIALS
                ? self::IDENTITY_FAILED_MESSAGE
                : $e->getMessage();

            throw ValidationException::withMessages(['login' => $message]);
        }

        $user = $this->synchronizer->sync($profile);

        if ($profile->token) {
            session()->put(self::SSO_TOKEN_SESSION_KEY, $profile->token);
        }

        return $user;
    }
}
