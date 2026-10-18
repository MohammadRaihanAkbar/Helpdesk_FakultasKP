<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Faculty;
use Illuminate\Support\Facades\DB;

/**
 * Katalog Kategori Layanan Standar (PRD v6.2 bagian 2.7).
 *
 * Per fakultas: 9 Kelompok Layanan + 3 Jenis Masalah = 12 record (11 bisa dipilih / "leaf").
 * 6 fakultas x 12 = 72 record categories.
 *
 * Catatan: angka SLA masih USULAN, perlu dikonfirmasi ke fakultas.
 *
 * Kenapa dipindah dari CategoryCatalogSeeder ke Service?
 *   Logika ini sekarang dipakai di DUA tempat:
 *     1. CategoryCatalogSeeder  -> saat instalasi awal (6 fakultas)
 *     2. Model Faculty (event "created") -> setiap fakultas baru (F-SYS-01)
 *   Seeder sebaiknya hanya untuk mengisi data awal, bukan dipanggil dari kode aplikasi.
 *   Aturan bisnis yang dipakai lebih dari satu tempat diletakkan di app/Services (rencana teknis bab 2).
 */
class CategoryCatalogService
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

    /**
     * Salin katalog standar ke 1 fakultas.
     *
     * Aman dijalankan ulang (idempotent): kategori yang sudah ada TIDAK ditimpa
     * (jadi SLA yang sudah diubah Admin Fakultas tidak kembali ke usulan awal).
     *
     * @param  Faculty|int  $faculty  model fakultas atau id-nya
     */
    public function seedForFaculty(Faculty|int $faculty): void
    {
        $facultyId = $faculty instanceof Faculty ? $faculty->id : $faculty;

        // Transaksi: kalau ada satu insert yang gagal, semuanya dibatalkan,
        // sehingga tidak ada fakultas dengan katalog setengah jadi.
        DB::transaction(function () use ($facultyId) {
            foreach (self::CATALOG as $group) {
                // 1) Kelompok Layanan (parent_id = NULL)
                $groupCategory = $this->firstOrCreateCategory($facultyId, null, $group);

                // 2) Audiens kelompok -> tabel category_audiences
                foreach ($group['audiences'] as $userType) {
                    $groupCategory->audiences()->firstOrCreate(['user_type' => $userType]);
                }

                // 3) Jenis Masalah (parent_id = id kelompok). Tidak punya baris audiens sendiri.
                foreach ($group['children'] ?? [] as $child) {
                    $this->firstOrCreateCategory($facultyId, $groupCategory->id, $child);
                }
            }
        });
    }

    /**
     * Ambil kategori jika sudah ada (faculty_id + name), kalau belum ada -> buat.
     *
     * firstOrCreate(kunci pencarian, nilai tambahan saat membuat):
     * pencarian memakai pasangan yang juga UNIQUE di migration (faculty_id, name).
     */
    private function firstOrCreateCategory(int $facultyId, ?int $parentId, array $data): Category
    {
        return Category::firstOrCreate(
            ['faculty_id' => $facultyId, 'name' => $data['name']],
            [
                'parent_id' => $parentId,
                'description' => $data['description'] ?? null,
                'sla_hours' => $data['sla'],
                'is_standard' => true,
                'is_active' => true,
            ],
        );
    }
}
