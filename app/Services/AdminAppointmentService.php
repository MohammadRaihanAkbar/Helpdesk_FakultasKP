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
 * Siapa yang boleh diubah (hanya Pelapor/Admin, bukan diri sendiri) dijaga UserPolicy::changeRole di controller.
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

    public function revoke(User $user): User
    {
        if (! $user->isAdmin()) {
            throw new BusinessRuleException('NOT_AN_ADMIN', 'Pengguna ini bukan Admin Fakultas, jadi tidak ada peran yang dicabut.', 422);
        }

        $user->forceFill(['role' => Role::EndUser, 'faculty_id' => null])->save();

        return $user;
    }
}
