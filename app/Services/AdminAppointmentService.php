<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\Faculty;
use App\Models\User;

/**
 * Tunjuk / cabut Admin Fakultas oleh Super Admin (US-18, F-SPA-02).
 *
 * Aturan PRD:
 *   - tunjuk  -> role = admin + faculty_id = fakultas yang dipilih
 *   - cabut   -> role = end_user + faculty_id = NULL
 *   - HANYA kolom role dan faculty_id yang berubah. user_type, NIM/NIP, origin_faculty_id, password: tetap.
 *     (Dosen yang ditunjuk tetap tercatat sebagai dosen dan tetap login lewat SSO.)
 *
 * Siapa yang boleh diubah (hanya Pelapor/Admin/Dekan, bukan diri sendiri) dijaga UserPolicy::changeRole di controller.
 *
 * [M6] Pola yang sama untuk DEKAN (role = dekan + faculty_id), plus createDekan(): akun lokal (email + password)
 * untuk dekan yang belum pernah login lewat SSO. Cabut -> kembali Pelapor, sama seperti Admin.
 *
 * Kenapa forceFill()? Supaya jelas di kode bahwa HANYA dua kolom ini yang disentuh, tanpa lewat data form.
 */
class AdminAppointmentService
{
    public function appoint(User $user, Faculty $faculty): User
    {
        $user->forceFill(['role' => Role::Admin, 'faculty_id' => $faculty->id])->save();

        return $user;
    }

    /** [M6] Tunjuk Dekan (atau pindah fakultas Dekan). Sama seperti appoint(): hanya role + faculty_id. */
    public function appointDekan(User $user, Faculty $faculty): User
    {
        $user->forceFill(['role' => Role::Dekan, 'faculty_id' => $faculty->id])->save();

        return $user;
    }

    /**
     * [M6] Buat akun LOKAL Dekan (login dengan email + password di halaman login yang sama).
     * NIP opsional: kalau diisi, dekan yang nanti login lewat SSO dengan NIP itu masuk ke akun INI
     * (SsoUserSynchronizer mencari akun lewat NIP dan tidak pernah mengubah role), jadi tidak ada akun ganda.
     *
     * @param  array{name: string, email: string, password: string, faculty_id: int, nip?: ?string}  $data
     */
    public function createDekan(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'nip' => $data['nip'] ?? null,
            'password' => $data['password'], // di-hash otomatis (cast 'hashed')
            'is_active' => true,
        ]);
        $user->forceFill(['role' => Role::Dekan, 'faculty_id' => $data['faculty_id']])->save();

        return $user;
    }

    public function revoke(User $user): User
    {
        if (! $user->isAdmin() && ! $user->isDekan()) {
            throw new BusinessRuleException('NOT_AN_ADMIN', 'Pengguna ini bukan Admin Fakultas atau Dekan, jadi tidak ada peran yang dicabut.', 422);
        }

        $user->forceFill(['role' => Role::EndUser, 'faculty_id' => null])->save();

        return $user;
    }
}
