<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Enums\UserType;
use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

/**
 * Spesialisasi Staff (US-13) dan ringkasan cakupan "Kelompok tanpa Staff" (F-ADM-09).
 *
 * Spesialisasi selalu di level KELOMPOK LAYANAN (parent_id NULL). Staff otomatis menerima tiket dari
 * semua Jenis Masalah di bawahnya (lihat scope Ticket::forStaff).
 */
class StaffSpecialtyService
{
    /** Urutan & judul kelompok checkbox (PRD 10.6). */
    public const BUCKETS = [
        'shared' => 'Mahasiswa & Dosen (bersama)',
        'mahasiswa' => 'Khusus Mahasiswa',
        'dosen' => 'Khusus Dosen',
        'alumni' => 'Khusus Alumni',
        'other' => 'Audiens lain',
        'extra' => 'Kategori Tambahan',
    ];

    /** Kelompok Layanan AKTIF di fakultas = pilihan spesialisasi yang sah. */
    public function activeGroups(int $facultyId): Collection
    {
        return Category::forFaculty($facultyId)
            ->topLevel()
            ->active()
            ->with(['audiences', 'children' => fn ($query) => $query->orderBy('id')])
            ->orderByDesc('is_standard')
            ->orderBy('id')
            ->get();
    }

    /**
     * Pilihan checkbox dikelompokkan menurut audiens:
     *   ['Khusus Mahasiswa' => Collection<Category>, ...]  (kelompok kosong tidak ikut)
     *
     * @return BaseCollection<string, Collection<int, Category>>
     */
    public function groupedOptions(int $facultyId, string $facultyCode): BaseCollection
    {
        $groups = $this->activeGroups($facultyId)->groupBy(fn (Category $group) => $this->bucketOf($group));

        return collect(self::BUCKETS)
            ->filter(fn ($label, $key) => $groups->has($key))
            ->mapWithKeys(fn ($label, $key) => [
                ($key === 'extra' ? "{$label} {$facultyCode}" : $label) => $groups[$key],
            ]);
    }

    /**
     * Simpan spesialisasi staff (mengganti yang lama).
     *
     * Hanya Kelompok Layanan aktif milik fakultas staff yang diterima; id lain (Jenis Masalah, fakultas lain,
     * kelompok nonaktif, id ngawur) -> INVALID_SPECIALTY. Pemetaan lama ke kelompok yang SEDANG nonaktif
     * tidak dihapus (checkbox-nya memang tidak tampil), supaya langsung berlaku lagi saat kelompok diaktifkan.
     *
     * @param  list<int>  $categoryIds
     */
    public function sync(User $staff, array $categoryIds): void
    {
        $activeIds = $this->activeGroups($staff->faculty_id)->modelKeys();
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));

        if (array_diff($categoryIds, $activeIds) !== []) {
            throw BusinessRuleException::invalidSpecialty();
        }

        $keptInactive = $staff->specialties()->whereNotIn('categories.id', $activeIds)->pluck('categories.id')->all();

        DB::transaction(fn () => $staff->specialties()->sync([...$categoryIds, ...$keptInactive]));
    }

    /**
     * Ringkasan cakupan: setiap Kelompok Layanan aktif + jumlah Staff AKTIF yang memegangnya
     * + jumlah tiket "Menunggu" di kelompok itu. Kelompok dengan 0 staff = "Kelompok tanpa Staff".
     *
     * @return BaseCollection<int, array{group: Category, staff_count: int, waiting_count: int}>
     */
    public function coverage(int $facultyId): BaseCollection
    {
        $groups = $this->activeGroups($facultyId)->loadCount([
            'staff as active_staff_count' => fn ($query) => $query->where('role', Role::Staff)->where('is_active', true),
        ]);

        // Tiket menunggu per kelompok: COALESCE(parent_id, id) = id kelompok (sama seperti antrean staff).
        $waiting = Ticket::query()
            ->join('categories', 'categories.id', '=', 'tickets.category_id')
            ->where('tickets.faculty_id', $facultyId)
            ->where('tickets.status', TicketStatus::Menunggu)
            ->selectRaw('COALESCE(categories.parent_id, categories.id) as group_id, COUNT(*) as total')
            ->groupBy('group_id')
            ->pluck('total', 'group_id');

        return $groups->map(fn (Category $group) => [
            'group' => $group,
            'staff_count' => (int) $group->active_staff_count,
            'waiting_count' => (int) ($waiting[$group->id] ?? 0),
        ])->values();
    }

    private function bucketOf(Category $group): string
    {
        if (! $group->is_standard) {
            return 'extra';
        }

        $types = collect($group->audienceTypes())->map->value->sort()->values()->all();

        return match ($types) {
            [UserType::Dosen->value, UserType::Mahasiswa->value] => 'shared',
            [UserType::Mahasiswa->value] => 'mahasiswa',
            [UserType::Dosen->value] => 'dosen',
            [UserType::Alumni->value] => 'alumni',
            default => 'other',
        };
    }
}
