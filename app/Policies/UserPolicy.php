<?php

namespace App\Policies;

use App\Models\User;

/**
 * Izin Admin Fakultas terhadap akun Staff (US-12, US-13).
 *
 * Admin hanya boleh menyentuh akun ber-peran staff DI FAKULTASNYA sendiri.
 * Akun admin lain, pelapor, atau staff fakultas lain -> 403.
 */
class UserPolicy
{
    public function manageStaff(User $admin, User $staff): bool
    {
        return $admin->isAdmin()
            && $staff->isStaff()
            && $admin->faculty_id !== null
            && $admin->faculty_id === $staff->faculty_id;
    }
}
