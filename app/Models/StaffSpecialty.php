<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabel 5: staff_specialties — pivot Staff <-> Kelompok Layanan.
 *
 * Biasanya cukup lewat $user->specialties() (BelongsToMany). Model ini tetap dibuat
 * supaya mudah dipakai sebagai subquery (lihat Ticket::forStaff) dan sesuai daftar model PRD 11.3.
 */
#[Fillable(['staff_id', 'category_id'])]
class StaffSpecialty extends Model
{
    /** Tabel ini hanya punya created_at (PRD), tidak ada updated_at. */
    const UPDATED_AT = null;

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
