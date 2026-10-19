<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aturan Pengaturan Layanan oleh Admin Fakultas (US-11, PRD 2.7 aturan 1–11, F-ADM-04, F-SYS-04).
 *
 * Controller hanya menerima request dan memanggil method di sini; SEMUA aturan bisnis ada di satu tempat,
 * jadi mudah diuji dan nanti bisa dipakai ulang oleh API (PRD bab 9).
 *
 * Ringkasan aturan:
 *   Kategori STANDAR (is_standard = true, hasil seed)
 *     - hanya Target SLA, deskripsi, dan Aktif yang boleh diubah
 *     - nama/induk/audiens terkunci, tidak bisa dihapus -> STANDARD_CATEGORY_LOCKED
 *   Kategori TAMBAHAN (is_standard = false)
 *     - induk: kosong (= Kelompok baru), kelompok tambahan, atau kelompok standar yang SUDAH punya Jenis Masalah
 *     - maksimal 2 tingkat (Jenis Masalah tidak boleh punya anak)
 *     - audiens wajib untuk Kelompok; Jenis Masalah mewarisi audiens induk (tidak punya baris audiens sendiri)
 *     - SLA Jenis Masalah baru default = SLA induk
 *     - hapus hanya kalau belum pernah dipakai tiket (kalau sudah -> CATEGORY_IN_USE, cukup nonaktifkan)
 */
class CategoryManager
{
    /**
     * Pohon kategori satu fakultas: Kelompok Layanan beserta Jenis Masalah, audiens, dan jumlah tiket.
     * Urutan: kategori standar dulu (sesuai urutan seed), lalu kategori tambahan.
     *
     * @return Collection<int, Category>
     */
    public function tree(int $facultyId): Collection
    {
        return Category::forFaculty($facultyId)
            ->topLevel()
            ->with([
                'audiences',
                'children' => fn ($query) => $query->withCount('tickets')->orderBy('id'),
            ])
            ->withCount('tickets')
            ->orderByDesc('is_standard')
            ->orderBy('id')
            ->get();
    }

    /**
     * Pilihan filter "Kelompok / Jenis Masalah" (Monitor Antrean, Laporan, Riwayat Staff — M4):
     *   [id kelompok => 'Sistem Akademik & Perkuliahan', id jenis => '— KRS belum di-ACC', ...]
     * Kategori NONAKTIF ikut tampil, karena tiket lama tetap memakainya.
     *
     * @param  list<int>|null  $onlyGroupIds  batasi ke kelompok tertentu (mis. spesialisasi staff); null = semua
     * @return array<int, string>
     */
    public function filterOptions(int $facultyId, ?array $onlyGroupIds = null): array
    {
        $options = [];

        $groups = Category::forFaculty($facultyId)
            ->topLevel()
            ->when($onlyGroupIds !== null, fn ($query) => $query->whereIn('id', $onlyGroupIds))
            ->with(['children' => fn ($query) => $query->orderBy('id')])
            ->orderByDesc('is_standard')
            ->orderBy('id')
            ->get();

        foreach ($groups as $group) {
            $options[$group->id] = $group->name;
            foreach ($group->children as $child) {
                $options[$child->id] = '— '.$child->name;
            }
        }

        return $options;
    }

    /**
     * Kelompok yang boleh menjadi INDUK kategori tambahan (PRD 2.7 aturan 3):
     * kelompok tambahan, atau kelompok standar yang sudah punya Jenis Masalah.
     *
     * @return Collection<int, Category>
     */
    public function parentOptions(int $facultyId, ?Category $except = null): Collection
    {
        return Category::forFaculty($facultyId)
            ->topLevel()
            ->where(fn ($query) => $query->where('is_standard', false)->orWhereHas('children'))
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->orderByDesc('is_standard')
            ->orderBy('id')
            ->get();
    }

    /**
     * Buat kategori tambahan untuk fakultas admin.
     *
     * @param  array{name: string, description?: ?string, parent_id?: ?int, audiences?: list<string>, sla_hours?: ?int, is_active?: bool}  $data
     */
    public function createExtra(int $facultyId, array $data): Category
    {
        $parent = $this->resolveParent($facultyId, $data['parent_id'] ?? null);

        return DB::transaction(function () use ($facultyId, $data, $parent) {
            $category = Category::create([
                'faculty_id' => $facultyId,                 // selalu fakultas admin, bukan dari input
                'parent_id' => $parent?->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                // Aturan 7: SLA Jenis Masalah default mengikuti SLA kelompok induk.
                'sla_hours' => $data['sla_hours'] ?? $parent?->sla_hours ?? 72,
                'is_standard' => false,                     // kategori buatan admin TIDAK PERNAH standar
                'is_active' => $data['is_active'] ?? true,
            ]);

            if ($parent === null) {
                $this->syncAudiences($category, $data['audiences'] ?? []);
            }

            return $category;
        });
    }

    /**
     * Ubah kategori. Standar: hanya SLA, deskripsi, Aktif. Tambahan: semua field.
     *
     * @param  array<string, mixed>  $data  sudah divalidasi Form Request
     */
    public function update(Category $category, array $data): Category
    {
        if ($category->is_standard) {
            $this->guardStandardFields($category, $data);

            $category->update(collect($data)->only(['sla_hours', 'description', 'is_active'])->all());

            return $category;
        }

        $parent = $this->resolveParent($category->faculty_id, $data['parent_id'] ?? null, $category);

        return DB::transaction(function () use ($category, $data, $parent) {
            $category->update([
                'parent_id' => $parent?->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'sla_hours' => $data['sla_hours'] ?? $parent?->sla_hours ?? $category->sla_hours,
                'is_active' => $data['is_active'] ?? $category->is_active,
            ]);

            if ($parent === null) {
                $this->syncAudiences($category, $data['audiences'] ?? []);
            } else {
                // Menjadi Jenis Masalah: audiens diwarisi dari induk, baris audiens lama dibuang.
                $category->audiences()->delete();
            }

            return $category;
        });
    }

    /**
     * Aktifkan / nonaktifkan (berlaku untuk standar maupun tambahan).
     *
     * Status anak SENGAJA tidak ditimpa: Jenis Masalah ikut tersembunyi karena induknya Off
     * (Category::isEffectivelyActive), dan saat kelompok diaktifkan lagi, status anak kembali seperti semula.
     */
    public function setActive(Category $category, bool $active): Category
    {
        $category->update(['is_active' => $active]);

        return $category;
    }

    /** Hapus kategori tambahan yang belum pernah dipakai tiket (beserta Jenis Masalah-nya). */
    public function delete(Category $category): void
    {
        if ($category->is_standard) {
            throw BusinessRuleException::standardCategoryLocked();
        }

        if ($category->isUsedByTickets()) {
            throw BusinessRuleException::categoryInUse();
        }

        DB::transaction(function () use ($category) {
            // parent_id memakai restrictOnDelete, jadi anak dihapus dulu baru induknya.
            // Baris audiens & spesialisasi staff ikut terhapus (cascadeOnDelete di migration).
            $category->children()->delete();
            $category->delete();
        });
    }

    // ------------------------------------------------------------------
    // Pembantu
    // ------------------------------------------------------------------

    /**
     * Ubah parent_id dari input menjadi model induk yang SAH, atau tolak.
     *
     * @param  Category|null  $self  kategori yang sedang diedit (null saat membuat baru)
     */
    private function resolveParent(int $facultyId, mixed $parentId, ?Category $self = null): ?Category
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }

        $parent = Category::find($parentId);

        if ($parent === null || $parent->faculty_id !== $facultyId) {
            throw BusinessRuleException::invalidParent('Kelompok induk tidak ditemukan di fakultas Anda.');
        }

        if ($self !== null && $parent->is($self)) {
            throw BusinessRuleException::invalidParent('Kategori tidak bisa menjadi induk bagi dirinya sendiri.');
        }

        if (! $parent->isGroup()) {
            throw BusinessRuleException::invalidParent('Hierarki maksimal 2 tingkat: Jenis Masalah tidak boleh punya anak.');
        }

        if ($parent->is_standard && ! $parent->children()->exists()) {
            throw BusinessRuleException::invalidParent(
                'Kelompok standar yang tidak memiliki Jenis Masalah tidak dapat diberi anak, agar tetap bisa dipilih langsung dan sebanding antar-fakultas.'
            );
        }

        if ($self !== null && $self->children()->exists()) {
            throw BusinessRuleException::invalidParent(
                'Kelompok ini masih memiliki Jenis Masalah, jadi tidak bisa dipindah menjadi Jenis Masalah (maksimal 2 tingkat).'
            );
        }

        return $parent;
    }

    /** Field terkunci kategori standar boleh ikut dikirim asalkan nilainya TIDAK berubah. */
    private function guardStandardFields(Category $category, array $data): void
    {
        $changed = (array_key_exists('name', $data) && $data['name'] !== $category->name)
            || (array_key_exists('parent_id', $data) && (int) $data['parent_id'] !== (int) $category->parent_id)
            || (array_key_exists('audiences', $data) && $this->normalize($data['audiences'] ?? []) !== $this->normalize(
                $category->audiences->pluck('user_type')->map->value->all()
            ));

        if ($changed) {
            throw BusinessRuleException::standardCategoryFieldLocked();
        }
    }

    /** @param  list<string>  $audiences */
    private function syncAudiences(Category $category, array $audiences): void
    {
        $category->audiences()->delete();

        foreach (array_unique($audiences) as $userType) {
            $category->audiences()->create(['user_type' => $userType]);
        }
    }

    /** @return list<string> */
    private function normalize(array $values): array
    {
        $values = array_values(array_unique(array_map('strval', $values)));
        sort($values);

        return $values;
    }
}
