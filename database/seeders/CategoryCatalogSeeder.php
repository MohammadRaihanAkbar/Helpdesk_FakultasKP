<?php

namespace Database\Seeders;

use App\Services\CategoryCatalogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Katalog Kategori Layanan Standar (PRD v6.2 bagian 2.7) untuk 6 fakultas awal.
 *
 * Isi katalog & logika penyalinannya sekarang ada di App\Services\CategoryCatalogService,
 * karena dipakai juga saat Super Admin menambah fakultas baru (F-SYS-01: katalog otomatis tersalin
 * lewat event "created" di model Faculty). Seeder ini cukup memanggil service tersebut.
 *
 * Kenapa masih perlu? FacultySeeder memakai DB::table() yang tidak memicu event model,
 * jadi 6 fakultas awal belum punya katalog sampai seeder ini dijalankan.
 */
class CategoryCatalogSeeder extends Seeder
{
    /**
     * Laravel otomatis menyuntikkan (inject) CategoryCatalogService ke parameter run().
     */
    public function run(CategoryCatalogService $catalog): void
    {
        foreach (DB::table('faculties')->pluck('id') as $facultyId) {
            $catalog->seedForFaculty($facultyId);
        }
    }
}
