<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Faculty;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Master data fakultas untuk Super Admin (US-17, F-SPA-01, F-SPA-03).
 *
 *   create()        tambah fakultas -> katalog standar ikut tersalin (hook Faculty::created)
 *   update()        ubah kode, nama, slug, deskripsi, status aktif
 *   overview()      daftar fakultas + statistik ringkas per baris (Admin, Staff, Tiket, Kelompok tanpa Staff)
 *   coverageGaps()  fakultas AKTIF yang punya Kelompok Layanan tanpa Staff (dashboard Super Admin)
 *
 * Super Admin TIDAK mengatur isi kategori (PRD 2.4). Penyalinan katalog dikerjakan sistem lewat event model
 * di App\Models\Faculty, jadi cukup Faculty::create().
 */
class FacultyService
{
    public function __construct(private readonly StaffSpecialtyService $specialties) {}

    /**
     * Fakultas baru + katalognya disimpan dalam SATU transaksi: kalau penyalinan katalog gagal, fakultasnya
     * ikut batal, jadi tidak ada fakultas "kosong" tanpa kategori (F-SYS-01).
     *
     * @param  array{code: string, name: string, slug: string, description?: ?string, is_active?: bool}  $data
     */
    public function create(array $data): Faculty
    {
        return DB::transaction(fn () => Faculty::create($data));
    }

    /** @param  array{code: string, name: string, slug: string, description?: ?string, is_active?: bool}  $data */
    public function update(Faculty $faculty, array $data): Faculty
    {
        $faculty->update($data);

        return $faculty;
    }

    /**
     * Semua fakultas + statistik ringkas US-17:
     *   admins_count         jumlah Admin Fakultas
     *   staff_count          jumlah Staff (semua), active_staff_count = yang aktif
     *   tickets_count        total tiket yang ditujukan ke fakultas itu
     *   uncovered_count      Kelompok Layanan AKTIF tanpa satu pun Staff aktif (celah coverage)
     *
     * Dihitung dengan beberapa query agregat (bukan satu query per fakultas).
     *
     * @return Collection<int, Faculty>
     */
    public function overview(): Collection
    {
        $uncovered = $this->uncoveredGroupCounts();

        return Faculty::query()
            ->withCount([
                'admins',
                'staff',
                'staff as active_staff_count' => fn ($query) => $query->where('is_active', true),
                'tickets',
            ])
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get()
            ->each(fn (Faculty $faculty) => $faculty->setAttribute('uncovered_count', (int) ($uncovered[$faculty->id] ?? 0)));
    }

    /**
     * Fakultas aktif yang punya celah coverage, beserta nama kelompoknya dan jumlah tiket yang sedang menunggu
     * di kelompok itu (PRD 10.9). Hanya informasi: penugasan Staff tetap wewenang Admin Fakultas.
     *
     * @return Collection<int, array{faculty: Faculty, groups: Collection<int, Category>, waiting_count: int}>
     */
    public function coverageGaps(): Collection
    {
        return $this->overview()
            ->filter(fn (Faculty $faculty) => $faculty->is_active && $faculty->uncovered_count > 0)
            ->map(function (Faculty $faculty) {
                $gaps = $this->specialties->coverage($faculty->id)->where('staff_count', 0);

                return [
                    'faculty' => $faculty,
                    'groups' => $gaps->pluck('group')->values(),
                    'waiting_count' => (int) $gaps->sum('waiting_count'),
                ];
            })
            ->values();
    }

    /**
     * [faculty_id => jumlah Kelompok Layanan aktif tanpa Staff aktif], satu query untuk semua fakultas.
     * Aturannya sama dengan StaffSpecialtyService::coverage() (yang menghitung per fakultas).
     *
     * @return Collection<int, int>
     */
    private function uncoveredGroupCounts(): Collection
    {
        return Category::query()
            ->topLevel()
            ->active()
            ->whereDoesntHave('staff', fn ($query) => $query->where('role', Role::Staff)->where('is_active', true))
            ->selectRaw('faculty_id, COUNT(*) as total')
            ->groupBy('faculty_id')
            ->pluck('total', 'faculty_id');
    }
}
