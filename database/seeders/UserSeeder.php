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
 *  - [M6] 1 Dekan FITK (dekan.fitk@uinssc.test): hanya dashboard statistik + laporan & ekspor FITK
 *  - 5 Staff FITK + spesialisasinya (tabel staff_specialties), 1 di antaranya nonaktif
 *  - 1 Staff FEBI (untuk mencoba batas lintas fakultas)
 *  - 4 pelapor: mahasiswa, dosen, alumni lulus 2023 (SSO), alumni lulus 2019 (hidden link)
 *  - 7 pelapor tambahan (M4) sebagai pemilik tiket riwayat beberapa bulan untuk grafik & laporan Admin.
 *    Mereka tidak ada di simulasi SSO, jadi tidak dipakai untuk login.
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

        // ---------- 2b. [M6] Dekan (faculty_id = fakultas yang dipimpin, hanya memantau) ----------
        $this->upsertUser([
            'name' => 'Dr. Hj. Dekan FITK', 'email' => 'dekan.fitk@uinssc.test',
            'role' => 'dekan', 'faculty_id' => $fac['FITK'], 'password' => $password,
        ]);

        // ---------- 3. Staff + spesialisasi ----------
        // Spesialisasi = Kelompok Layanan (parent_id NULL) yang boleh dilihat antreannya.
        // "Integritas Dosen" (sensitif) sengaja hanya dipegang staff Dekanat (PRD 2.7 aturan 10).
        $staffList = [
            'staff.akademik.fitk@uinssc.test' => ['Staff Akademik FITK', 'FITK', true, [
                'Sistem Akademik & Perkuliahan', 'Jadwal Mengajar',
            ]],
            'staff.umum.fitk@uinssc.test' => ['Staff Umum FITK', 'FITK', true, [
                'Pelayanan Administrasi & Birokrasi', 'Keuangan & UKT',
                'Fasilitas, Sarana & Prasarana', 'Akun, Jaringan & IT', 'Laboratorium Microteaching',
            ]],
            'staff.alumni.fitk@uinssc.test' => ['Staff Layanan Alumni FITK', 'FITK', true, [
                'Transkrip Nilai', 'Kesalahan Data PDDIKTI',
            ]],
            'staff.dekanat.fitk@uinssc.test' => ['Staff Dekanat FITK', 'FITK', true, [
                'Integritas Dosen',
            ]],
            'staff.cuti.fitk@uinssc.test' => ['Staff Cuti FITK', 'FITK', false, [ // akun NONAKTIF: tidak bisa login
                'Keuangan & UKT',
            ]],
            'staff.umum.febi@uinssc.test' => ['Staff Umum FEBI', 'FEBI', true, [
                'Pelayanan Administrasi & Birokrasi', 'Akun, Jaringan & IT',
            ]],
        ];

        foreach ($staffList as $email => [$name, $code, $active, $groups]) {
            $staffId = $this->upsertUser([
                'name' => $name, 'email' => $email, 'is_active' => $active,
                'role' => 'staff', 'faculty_id' => $fac[$code], 'password' => $password,
            ]);

            $categoryIds = DB::table('categories')
                ->where('faculty_id', $fac[$code])
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
        $this->upsertUser([ // Mahasiswa aktif: login SSO pakai NIM
            'name' => 'Ahmad Mahasiswa', 'email' => 'mahasiswa@uinssc.test',
            'role' => 'end_user', 'user_type' => 'mahasiswa',
            'nim' => '2210110001', 'program_studi' => 'Pendidikan Agama Islam',
            'origin_faculty_id' => $fac['FITK'], 'password' => $password,
        ]);

        $this->upsertUser([ // Dosen: login SSO pakai NIP (NIDN juga diterima)
            'name' => 'Dr. Budi Dosen', 'email' => 'dosen@uinssc.test',
            'role' => 'end_user', 'user_type' => 'dosen',
            'nip' => '198804122015031001', 'nidn' => '0412038801', 'program_studi' => 'Pendidikan Bahasa Arab',
            'origin_faculty_id' => $fac['FITK'], 'password' => $password,
        ]);

        $this->upsertUser([ // Alumni lulus 2023 (>= 2022): datanya ADA di SSO, login pakai NIM
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

        // ---------- 5. Pelapor tambahan untuk data laporan (M4) ----------
        // Pemilik tiket riwayat di DemoTicketSeeder, supaya data akun demo di atas (Ahmad, Budi, ...) tidak berubah.
        $extra = [
            ['Siti Aminah', 'siti.aminah@uinssc.test', 'mahasiswa', ['nim' => '2210110002', 'program_studi' => 'Pendidikan Agama Islam']],
            ['Rizky Pratama', 'rizky.pratama@uinssc.test', 'mahasiswa', ['nim' => '2210120015', 'program_studi' => 'Tadris Matematika']],
            ['Nurul Hidayah', 'nurul.hidayah@uinssc.test', 'mahasiswa', ['nim' => '2310130021', 'program_studi' => 'Pendidikan Bahasa Inggris']],
            ['Fajar Ramadhan', 'fajar.ramadhan@uinssc.test', 'mahasiswa', ['nim' => '2310110034', 'program_studi' => 'Pendidikan Guru MI']],
            ['Dr. Hendra Saputra', 'hendra.saputra@uinssc.test', 'dosen', ['nip' => '198507152010121002', 'program_studi' => 'Tadris Matematika']],
            ['Lina Marlina, M.Pd.', 'lina.marlina@uinssc.test', 'dosen', ['nip' => '199011022019032001', 'program_studi' => 'Pendidikan Bahasa Inggris']],
            ['Yusuf Alumni', 'yusuf.alumni2022@uinssc.test', 'alumni', ['nim' => '1810110077', 'program_studi' => 'Pendidikan Agama Islam', 'graduation_year' => 2022]],
        ];
        foreach ($extra as [$name, $email, $type, $identity]) {
            $this->upsertUser([
                'name' => $name, 'email' => $email, 'role' => 'end_user', 'user_type' => $type,
                'origin_faculty_id' => $fac['FITK'], 'password' => $password,
            ] + $identity);
        }
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
