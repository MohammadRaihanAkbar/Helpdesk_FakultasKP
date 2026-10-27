<?php

namespace App\Policies;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Enums\UserType;
use App\Models\User;

/**
 * Izin terhadap akun pengguna lain.
 *
 *   manageStaff  Admin Fakultas -> akun Staff (US-12, US-13). Admin hanya boleh menyentuh akun ber-peran staff
 *                DI FAKULTASNYA sendiri. Akun admin lain, pelapor, atau staff fakultas lain -> 403.
 *   changeRole   Super Admin -> tunjuk/cabut Admin Fakultas (US-18, M5) dan Dekan (M6).
 *   verifyAlumni [M6] Admin Fakultas -> setujui/tolak pendaftar alumni yang fakultas ASAL-nya = fakultas admin.
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
     * Tunjuk / cabut Admin Fakultas (US-18) atau Dekan (M6). Hanya Super Admin, dan hanya untuk akun Pelapor,
     * Admin Fakultas, atau Dekan.
     *
     * Akun Staff tidak boleh diubah di sini: akun & spesialisasi Staff adalah wewenang Admin Fakultas (PRD 2.4).
     * Akun Super Admin (termasuk dirinya sendiri) juga tidak, supaya Super Admin tidak mengunci dirinya keluar.
     */
    public function changeRole(User $superAdmin, User $target): bool
    {
        return $superAdmin->isSuperAdmin()
            && $superAdmin->id !== $target->id
            && in_array($target->role, [Role::EndUser, Role::Admin, Role::Dekan], true);
    }

    /**
     * [M6] Setujui / tolak pendaftaran alumni hidden link. Hanya Admin Fakultas dari fakultas ASAL alumni
     * (origin_faculty_id), dan hanya untuk pendaftaran yang masih menunggu verifikasi.
     */
    public function verifyAlumni(User $admin, User $alumni): bool
    {
        return $admin->isAdmin()
            && $admin->faculty_id !== null
            && $alumni->user_type === UserType::Alumni
            && $alumni->origin_faculty_id === $admin->faculty_id
            && $alumni->account_status === AccountStatus::Pending;
    }
}
