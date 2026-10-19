<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Services\CategoryManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Contoh KATEGORI TAMBAHAN FITK (is_standard = false), supaya halaman Pengaturan Layanan & form tiket
 * langsung memperlihatkan bedanya kategori standar dan tambahan (PRD 2.5: contoh "Laboratorium Microteaching").
 *
 *   Laboratorium Microteaching      (Kelompok tambahan, audiens Mahasiswa + Dosen, SLA 48 jam)
 *     └ Peminjaman ruang microteaching   (SLA 24 jam)
 *     └ Peralatan lab rusak              (SLA default = SLA induk, 48 jam)
 *
 * Dibuat lewat CategoryManager (service yang sama dengan menu Admin), jadi aturan bisnisnya ikut teruji.
 * HANYA untuk development.
 */
class DemoCategorySeeder extends Seeder
{
    public function run(CategoryManager $categories): void
    {
        $fitk = (int) DB::table('faculties')->where('code', 'FITK')->value('id');

        if (Category::where('faculty_id', $fitk)->where('name', 'Laboratorium Microteaching')->exists()) {
            return; // sudah ada -> jangan dobel
        }

        $lab = $categories->createExtra($fitk, [
            'name' => 'Laboratorium Microteaching',
            'description' => 'Peminjaman ruang dan peralatan Laboratorium Microteaching untuk praktik mengajar',
            'audiences' => ['mahasiswa', 'dosen'],
            'sla_hours' => 48,
        ]);

        $categories->createExtra($fitk, [
            'name' => 'Peminjaman ruang microteaching',
            'description' => 'Jadwal pemakaian ruang microteaching bentrok atau belum dikonfirmasi',
            'parent_id' => $lab->id,
            'sla_hours' => 24,
        ]);

        $categories->createExtra($fitk, [
            'name' => 'Peralatan lab rusak',
            'description' => 'Kamera, mikrofon, atau perangkat rekam microteaching tidak berfungsi',
            'parent_id' => $lab->id, // SLA dikosongkan -> ikut induk (48 jam)
        ]);
    }
}
