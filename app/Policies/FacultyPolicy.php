<?php

namespace App\Policies;

use App\Models\Faculty;
use App\Models\User;

/**
 * Master data fakultas (US-17, F-SPA-01): HANYA Super Admin.
 *
 * Route /super-admin/* sudah dijaga middleware role:super_admin. Policy ini lapisan kedua, supaya aturan
 * "siapa boleh mengubah fakultas" tertulis di satu tempat dan tetap berlaku kalau suatu saat ada route lain
 * (mis. API) yang memakai controller/service yang sama.
 *
 * Controller memakai: Gate::authorize('create', Faculty::class) / Gate::authorize('update', $faculty).
 */
class FacultyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Faculty $faculty): bool
    {
        return $user->isSuperAdmin();
    }
}
