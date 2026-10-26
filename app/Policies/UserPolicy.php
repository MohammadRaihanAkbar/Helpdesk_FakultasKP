<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Izin terhadap akun pengguna lain.
 *
 *   manageStaff  Admin Fakultas -> akun Staff (US-12, US-13). Admin hanya boleh menyentuh akun ber-peran staff
 *                DI FAKULTASNYA sendiri. Akun admin lain, pelapor, atau staff fakultas lain -> 403.
 *   changeRole   Super Admin -> tunjuk/cabut Admin Fakultas (US-18, M5).
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

    /**
     * Tunjuk / cabut Admin Fakultas (US-18). Hanya Super Admin, dan hanya untuk akun Pelapor atau Admin Fakultas.
     *
     * Akun Staff tidak boleh diubah di sini: akun & spesialisasi Staff adalah wewenang Admin Fakultas (PRD 2.4).
     * Akun Super Admin (termasuk dirinya sendiri) juga tidak, supaya Super Admin tidak mengunci dirinya keluar.
     */
    public function changeRole(User $superAdmin, User $target): bool
    {
        return $superAdmin->isSuperAdmin()
            && $superAdmin->id !== $target->id
            && in_array($target->role, [Role::EndUser, Role::Admin], true);
    }
}
