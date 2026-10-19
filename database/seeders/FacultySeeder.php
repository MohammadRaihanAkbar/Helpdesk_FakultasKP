<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data master 6 fakultas UIN SSC.
 * Pakai updateOrInsert berdasarkan "code" supaya aman dijalankan berulang (tidak dobel).
 */
class FacultySeeder extends Seeder
{
    public function run(): void
    {
        $faculties = [
            ['code' => 'FITK',   'name' => 'Fakultas Ilmu Tarbiyah dan Keguruan'],
            ['code' => 'FUA',    'name' => 'Fakultas Ushuluddin dan Adab'],
            ['code' => 'FDKI',   'name' => 'Fakultas Dakwah dan Komunikasi Islam'],
            ['code' => 'FEBI',   'name' => 'Fakultas Ekonomi dan Bisnis Islam'],
            ['code' => 'FASYA',  'name' => 'Fakultas Syariah'],
            ['code' => 'FASTIH', 'name' => 'Fakultas Sains, Teknologi, dan Ilmu Kesehatan'],
        ];

        foreach ($faculties as $f) {
            DB::table('faculties')->updateOrInsert(
                ['code' => $f['code']],                       // kunci pencarian
                [
                    'name' => $f['name'],
                    'slug' => strtolower($f['code']),   // fitk, fua, ...
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
