<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder utama. Jalankan dengan:  php artisan migrate:fresh --seed
 *
 * URUTAN PENTING! Tabel "anak" butuh data tabel "induk" dulu:
 *   faculties -> categories (+ audiens) -> users (+ spesialisasi) -> tiket contoh
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FacultySeeder::class,          // 6 fakultas
            CategoryCatalogSeeder::class,  // 12 kategori standar x 6 fakultas = 72 + audiensnya
            UserSeeder::class,             // akun contoh 4 role + 4 jenis pelapor
            DemoTicketSeeder::class,       // 4 tiket contoh (1 per status) -> HAPUS saat production
        ]);
    }
}
