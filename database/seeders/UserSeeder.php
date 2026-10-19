<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Akun contoh untuk development. Semua password: "password".
 *
 * Isinya:
 *  - 1 Super Admin
 *  - 1 Admin Fakultas per fakultas (6 akun)
 *  - 3 Staff FITK + spesialisasinya (tabel staff_specialties)
 *  - 4 pelapor: mahasiswa, dosen, alumni lulus 2023 (SSO), alumni lulus 2019 (hidden link)
 *
 * Catatan: di production, mahasiswa/dosen/alumni >= 2022 login lewat SSO, jadi password
 * mereka tidak dipakai. Di sini tetap diisi supaya bisa dites login lokal.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');
        $fac = DB::table('faculties')->pluck('id', 'code'); // ['FITK' => 1, 'FUA' => 2, ...]

        // ---------- 1. Super Admin (tidak terikat fakultas) ----------
        $this->upsertUser([
            'name' => 'Super Admin IT', 'email' => 'superadmin@uinssc.test',
            'role' => 'super_admin', 'password' => $password,
        ]);

        // ---------- 2. Admin Fakultas (faculty_id = fakultas penugasan) ----------
        foreach ($fac as $code => $facultyId) {
            $this->upsertUser([
                'name' => "Admin {$code}", 'email' => 'admin.'.strtolower($code).'@uinssc.test',
                'role' => 'admin', 'faculty_id' => $facultyId, 'password' => $password,
            ]);
        }

        // ---------- 3. Staff FITK + spesialisasi ----------
        // Spesialisasi = Kelompok Layanan (parent_id NULL) yang boleh dilihat antreannya
        $staffList = [
            'staff.akademik.fitk@uinssc.test' => ['Staff Akademik FITK', [
                'Sistem Akademik & Perkuliahan', 'Jadwal Mengajar', 'Integritas Dosen',
            ]],
            'staff.umum.fitk@uinssc.test' => ['Staff Umum FITK', [
                'Pelayanan Administrasi & Birokrasi', 'Keuangan & UKT',
                'Fasilitas, Sarana & Prasarana', 'Akun, Jaringan & IT',
            ]],
            'staff.alumni.fitk@uinssc.test' => ['Staff Layanan Alumni FITK', [
                'Transkrip Nilai', 'Kesalahan Data PDDIKTI',
            ]],
        ];

        foreach ($staffList as $email => [$name, $groups]) {
            $staffId = $this->upsertUser([
                'name' => $name, 'email' => $email,
                'role' => 'staff', 'faculty_id' => $fac['FITK'], 'password' => $password,
            ]);

            $categoryIds = DB::table('categories')
                ->where('faculty_id', $fac['FITK'])
                ->whereNull('parent_id')          // hanya Kelompok Layanan
                ->whereIn('name', $groups)
                ->pluck('id');

            foreach ($categoryIds as $categoryId) {
                DB::table('staff_specialties')->insertOrIgnore([
                    'staff_id' => $staffId, 'category_id' => $categoryId, 'created_at' => now(),
                ]);
            }
        }

        // ---------- 4. Pelapor (role end_user, faculty_id NULL, origin_faculty_id = fakultas asal) ----------
        $this->upsertUser([ // Mahasiswa aktif — login SSO pakai NIM
            'name' => 'Ahmad Mahasiswa', 'email' => 'mahasiswa@uinssc.test',
            'role' => 'end_user', 'user_type' => 'mahasiswa',
            'nim' => '2210110001', 'program_studi' => 'Pendidikan Agama Islam',
            'origin_faculty_id' => $fac['FITK'], 'password' => $password,
        ]);

        $this->upsertUser([ // Dosen — login SSO pakai NIDN
            'name' => 'Dr. Budi Dosen', 'email' => 'dosen@uinssc.test',
            'role' => 'end_user', 'user_type' => 'dosen',
            'nidn' => '0412038801', 'program_studi' => 'Pendidikan Bahasa Arab',
            'origin_faculty_id' => $fac['FITK'], 'password' => $password,
        ]);

        $this->upsertUser([ // Alumni lulus 2023 (>= 2022) — datanya ADA di SSO, login pakai NIM
            'name' => 'Citra Alumni SSO', 'email' => 'alumni2023@uinssc.test',
            'role' => 'end_user', 'user_type' => 'alumni', 'graduation_year' => 2023,
            'nim' => '1910110045', 'program_studi' => 'Tadris Matematika',
            'origin_faculty_id' => $fac['FITK'], 'password' => $password,
        ]);

        $this->upsertUser([ // Alumni lulus 2019 (<= 2021) — TIDAK ada di SSO, daftar lewat hidden link
            'name' => 'Dedi Alumni Lama', 'email' => 'dedi.alumni2019@example.com', // email pribadi
            'role' => 'end_user', 'user_type' => 'alumni', 'graduation_year' => 2019,
            'nim' => '1510110023', 'program_studi' => 'Pendidikan Agama Islam',
            'origin_faculty_id' => $fac['FITK'], 'password' => $password,
        ]);
    }

    /** Insert user baru atau update jika email sudah ada. Mengembalikan id user. */
    private function upsertUser(array $data): int
    {
        DB::table('users')->updateOrInsert(
            ['email' => $data['email']],
            array_merge(['is_active' => true, 'created_at' => now(), 'updated_at' => now()], $data)
        );

        return DB::table('users')->where('email', $data['email'])->value('id');
    }
}
