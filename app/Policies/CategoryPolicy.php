<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Siapa boleh mengelola kategori? Hanya Admin Fakultas, dan hanya kategori FAKULTASNYA sendiri
 * (PRD 2.3: akses Admin Fakultas terbatas pada faculty_id-nya).
 *
 * Laravel menemukan policy ini otomatis karena namanya "<Model>Policy" di folder app/Policies.
 * Dipakai di controller: Gate::authorize('manage', $category);  -> gagal = 403.
 */
class CategoryPolicy
{
    public function manage(User $user, Category $category): bool
    {
        return $user->isAdmin()
            && $user->faculty_id !== null
            && $user->faculty_id === $category->faculty_id;
    }
}
