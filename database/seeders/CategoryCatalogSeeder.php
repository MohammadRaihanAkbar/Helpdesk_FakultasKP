<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Katalog Kategori Layanan Standar (PRD v6.2 bagian 2.7).
 *
 * Per fakultas: 9 Kelompok Layanan + 3 Jenis Masalah = 12 record (11 bisa dipilih / "leaf").
 * 6 fakultas x 12 = 72 record categories.
 *
 * Catatan: angka SLA masih USULAN, perlu dikonfirmasi ke fakultas.
 *
 * Fungsi seedForFaculty() dibuat public static supaya nanti bisa dipanggil juga
 * saat Super Admin menambah fakultas baru (F-SYS-01: katalog otomatis tersalin).
 */
class CategoryCatalogSeeder extends Seeder
{
    /**
     * Isi katalog. Satu elemen = satu Kelompok Layanan.
     * 'audiences' hanya ditulis di kelompok; Jenis Masalah (children) mewarisi audiens induknya.
     */
    public const CATALOG = [
        [
            'name' => 'Sistem Akademik & Perkuliahan',
            'description' => 'Masalah sistem akademik dan perkuliahan mahasiswa',
            'sla' => 72, // hanya nilai default untuk Jenis Masalah baru (kelompok ini tidak dipilih langsung)
            'audiences' => ['mahasiswa'],
            'children' => [
                ['name' => 'Nilai belum muncul', 'sla' => 72, 'description' => 'Nilai mata kuliah semester lalu belum tampil di portal akademik'],
                ['name' => 'KRS belum di-ACC',   'sla' => 24, 'description' => 'KRS sudah diisi tetapi belum disetujui Dosen PA; mendesak selama masa KRS'],
                ['name' => 'Jadwal mata kuliah', 'sla' => 48, 'description' => 'Jadwal bentrok, ruang/jam kuliah belum muncul atau tidak sesuai'],
            ],
        ],
        ['name' => 'Pelayanan Administrasi & Birokrasi', 'sla' => 72,  'audiences' => ['mahasiswa', 'dosen'], 'description' => 'Surat keterangan, legalisir, alur persuratan/birokrasi yang lambat'],
        ['name' => 'Keuangan & UKT',                     'sla' => 72,  'audiences' => ['mahasiswa'],          'description' => 'Pembayaran UKT belum terverifikasi, permohonan keringanan/penyesuaian UKT'],
        ['name' => 'Integritas Dosen',                   'sla' => 120, 'audiences' => ['mahasiswa'],          'description' => 'Dosen sering tidak hadir tanpa kabar, perilaku tidak pantas (sensitif, butuh investigasi)'],
        ['name' => 'Fasilitas, Sarana & Prasarana',      'sla' => 72,  'audiences' => ['mahasiswa', 'dosen'], 'description' => 'Proyektor/AC kelas rusak, kebersihan ruang, fasilitas gedung fakultas'],
        ['name' => 'Akun, Jaringan & IT',                'sla' => 24,  'audiences' => ['mahasiswa', 'dosen'], 'description' => 'Tidak bisa login portal/SSO, Wi-Fi fakultas mati, email kampus bermasalah'],
        ['name' => 'Jadwal Mengajar',                    'sla' => 48,  'audiences' => ['dosen'],              'description' => 'Jadwal mengajar bentrok, ruang belum tersedia, perubahan jadwal mengajar'],
        ['name' => 'Transkrip Nilai',                    'sla' => 72,  'audiences' => ['alumni'],             'description' => 'Permohonan/legalisir transkrip, nilai pada transkrip tidak sesuai'],
        ['name' => 'Kesalahan Data PDDIKTI',             'sla' => 120, 'audiences' => ['alumni'],             'description' => 'Nama, tanggal lahir, atau status lulus di PDDIKTI salah'],
    ];

    public function run(): void
    {
        foreach (DB::table('faculties')->pluck('id') as $facultyId) {
            self::seedForFaculty($facultyId);
        }
    }

    /**
     * Salin katalog standar ke 1 fakultas.
     * Aman dijalankan ulang: kategori yang sudah ada TIDAK ditimpa
     * (jadi SLA yang sudah diubah Admin Fakultas tidak kembali ke usulan awal).
     */
    public static function seedForFaculty(int $facultyId): void
    {
        DB::transaction(function () use ($facultyId) {
            foreach (self::CATALOG as $group) {
                // 1) Kelompok Layanan (parent_id = NULL)
                $groupId = self::firstOrInsert($facultyId, null, $group);

                // 2) Audiens kelompok -> tabel category_audiences
                foreach ($group['audiences'] as $userType) {
                    DB::table('category_audiences')->insertOrIgnore([
                        'category_id' => $groupId,
                        'user_type'   => $userType,
                    ]);
                }

                // 3) Jenis Masalah (parent_id = id kelompok). Tidak punya baris audiens sendiri.
                foreach ($group['children'] ?? [] as $child) {
                    self::firstOrInsert($facultyId, $groupId, $child);
                }
            }
        });
    }

    /** Ambil id kategori jika sudah ada (faculty_id + name), kalau belum ada -> insert. */
    private static function firstOrInsert(int $facultyId, ?int $parentId, array $data): int
    {
        $existingId = DB::table('categories')
            ->where('faculty_id', $facultyId)
            ->where('name', $data['name'])
            ->value('id');

        if ($existingId) {
            return $existingId;
        }

        return DB::table('categories')->insertGetId([
            'faculty_id'  => $facultyId,
            'parent_id'   => $parentId,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'sla_hours'   => $data['sla'],
            'is_standard' => true,
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
}
