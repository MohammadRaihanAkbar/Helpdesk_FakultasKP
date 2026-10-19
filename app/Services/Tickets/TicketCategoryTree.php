<?php

namespace App\Services\Tickets;

use App\Enums\UserType;
use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\Faculty;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pilihan kategori di form Buat Tiket (US-03, US-05B, F-SYS-02, F-SYS-03) dan validasinya di server.
 *
 * Yang boleh tampil untuk seorang pelapor di sebuah fakultas:
 *   - Kelompok Layanan AKTIF milik fakultas itu yang audiensnya memuat jenis pelapor (user_type)
 *   - Kelompok yang punya Jenis Masalah -> hanya Jenis Masalah AKTIF yang tampil; kalau semuanya nonaktif,
 *     kelompok tidak ditampilkan sama sekali (PRD 2.7 aturan 8)
 *   - Kelompok tanpa Jenis Masalah -> bisa dipilih langsung
 */
class TicketCategoryTree
{
    /**
     * Pohon untuk SEMUA fakultas aktif sekaligus (di-embed sebagai JSON di form, jadi ganti fakultas
     * tidak perlu request baru):
     *   [faculty_id => [ ['id', 'name', 'description', 'sla_hours', 'children' => [...]], ... ]]
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function forUserType(UserType $userType): array
    {
        $groups = Category::query()
            ->topLevel()
            ->active()
            ->whereHas('faculty', fn ($query) => $query->where('is_active', true))
            ->whereHas('audiences', fn ($query) => $query->where('user_type', $userType))
            ->with(['children' => fn ($query) => $query->orderBy('id')])
            ->orderByDesc('is_standard')
            ->orderBy('id')
            ->get();

        $tree = [];

        foreach ($groups as $group) {
            // Kelompok yang punya anak tapi semua anaknya Off -> disembunyikan.
            $children = $group->children->where('is_active', true)->values();
            if ($group->children->isNotEmpty() && $children->isEmpty()) {
                continue;
            }

            $tree[$group->faculty_id][] = [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'sla_hours' => $group->sla_hours,
                'children' => $children->map(fn (Category $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'description' => $child->description,
                    'sla_hours' => $child->sla_hours,
                ])->all(),
            ];
        }

        return $tree;
    }

    /** @return Collection<int, Faculty> */
    public function faculties(): Collection
    {
        return Faculty::where('is_active', true)->orderBy('id')->get();
    }

    /**
     * Validasi server untuk kategori tiket. Lolos hanya jika kategori:
     *   leaf, milik fakultas tujuan, aktif (beserta induknya), dan audiensnya sesuai jenis pelapor.
     * Selain itu -> INVALID_CATEGORY (422). Pesan sengaja satu macam, supaya tidak membocorkan detail katalog.
     */
    public function assertSelectable(mixed $categoryId, mixed $facultyId, UserType $userType): Category
    {
        $category = is_numeric($categoryId) ? Category::with('parent', 'faculty')->find((int) $categoryId) : null;

        $valid = $category !== null
            && $category->faculty_id === (int) $facultyId
            && $category->faculty->is_active
            && $category->isSelectable()                // leaf + aktif + induk aktif
            && $category->isVisibleTo($userType);       // audiens kelompok memuat jenis pelapor

        if (! $valid) {
            throw BusinessRuleException::invalidCategory();
        }

        return $category;
    }
}
