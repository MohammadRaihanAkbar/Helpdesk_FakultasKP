<?php

namespace Database\Seeders;

use App\Services\Tickets\AttachmentStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 8 tiket contoh di FITK yang KONSISTEN dengan aturan bisnis, supaya setiap halaman M2 langsung berisi:
 *
 *  #  pelapor          kategori (leaf)                         status    yang ikut terisi
 *  -  ---------------  --------------------------------------  --------  -------------------------------------------
 *  1  Ahmad Mahasiswa  Sistem Akademik › KRS belum di-ACC      menunggu  lampiran form (PNG asli di disk privat)
 *  2  Ahmad Mahasiswa  Fasilitas, Sarana & Prasarana           diproses  thread: staff ↔ mahasiswa + 1 catatan internal
 *  3  Ahmad Mahasiswa  Sistem Akademik › Nilai belum muncul    selesai   bukti penyelesaian (PDF), rating 5
 *  4  Ahmad Mahasiswa  Sistem Akademik › Jadwal mata kuliah    ditolak   alasan baku "Salah fakultas/kategori" -> bisa Buat Ulang
 *  5  Ahmad Mahasiswa  Integritas Dosen (SENSITIF)             menunggu  hanya terlihat staff Dekanat
 *  6  Dr. Budi Dosen   Akun, Jaringan & IT                     diproses  thread, Ping admin, notifikasi
 *  7  Citra Alumni     Transkrip Nilai                         selesai   bukti penyelesaian, rating
 *  8  Dedi Alumni Lama Kesalahan Data PDDIKTI                  ditolak   alasan lain (tidak bisa Buat Ulang)
 *
 * Aturan yang dijaga: kategori selalu LEAF dan audiensnya cocok dengan jenis pelapor; sla_target_hours &
 * reporter_type = snapshot; handler punya spesialisasi kelompok tiket; nomor TKT-YYYYMM-XXXX urut per bulan
 * sesuai tanggal dibuat; resolved_at terisi untuk selesai/ditolak; berkas demo benar-benar ada di disk privat.
 *
 * HANYA untuk development. Jangan dijalankan di production.
 */
class DemoTicketSeeder extends Seeder
{
    /** PNG 1x1 piksel hijau (berkas gambar asli, kecil). */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private const PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    /** @var array<string, int> nomor urut terakhir per bulan, mis. ['202610' => 3] */
    private array $sequence = [];

    public function run(): void
    {
        if (DB::table('tickets')->exists()) {
            return; // sudah ada tiket -> jangan bikin dobel
        }

        $fitk = DB::table('faculties')->where('code', 'FITK')->value('id');
        $user = fn (string $email) => DB::table('users')->where('email', $email)->value('id');
        $cat = fn (string $name) => DB::table('categories')
            ->where('faculty_id', $fitk)->where('name', $name)->first(['id', 'sla_hours']);

        $mahasiswa = $user('mahasiswa@uinssc.test');
        $dosen = $user('dosen@uinssc.test');
        $alumni2023 = $user('alumni2023@uinssc.test');
        $alumni2019 = $user('dedi.alumni2019@example.com');
        $staffAkd = $user('staff.akademik.fitk@uinssc.test');
        $staffUmum = $user('staff.umum.fitk@uinssc.test');
        $staffAlm = $user('staff.alumni.fitk@uinssc.test');
        $adminFitk = $user('admin.fitk@uinssc.test');

        $now = now()->startOfMinute();

        // Dibuat berurutan dari yang PALING LAMA, supaya nomor tiket urut sesuai tanggal dibuat.

        // ===== 8) DITOLAK (alasan lain) — alumni lama, data tidak cocok =====
        $pdd = $cat('Kesalahan Data PDDIKTI');
        $created = $now->copy()->subDays(9);
        $this->ticket([
            'faculty_id' => $fitk, 'title' => 'Nama saya salah di PDDIKTI',
            'description' => 'Nama di PDDIKTI tertulis "Dedi Alumi", seharusnya "Dedi Alumni".',
            'status' => 'ditolak',
            'rejection_reason' => 'NIM tidak ditemukan di arsip akademik FITK. Silakan cek kembali fakultas asal.',
            'reporter_id' => $alumni2019, 'reporter_type' => 'alumni',
            'handler_id' => $staffAlm, 'assigned_at' => $created->copy()->addHours(8),
            'first_responded_at' => $created->copy()->addHours(9),
            'resolved_at' => $created->copy()->addHours(30), // ditolak juga mengisi resolved_at (waktu ditutup)
            'category_id' => $pdd->id, 'sla_target_hours' => $pdd->sla_hours,
        ], $created);

        // ===== 3) SELESAI — mahasiswa, bukti + rating =====
        $nilai = $cat('Nilai belum muncul');
        $created = $now->copy()->subDays(7);
        $t3 = $this->ticket([
            'faculty_id' => $fitk, 'title' => 'Nilai Metodologi Penelitian belum muncul di portal',
            'description' => 'Semester lalu saya sudah ujian, tetapi nilai Metodologi Penelitian belum tampil di portal akademik.',
            'status' => 'selesai',
            'reporter_id' => $mahasiswa, 'reporter_type' => 'mahasiswa',
            'handler_id' => $staffAkd, 'assigned_at' => $created->copy()->addHours(3),
            'first_responded_at' => $created->copy()->addHours(4),
            'resolved_at' => $created->copy()->addHours(30), // 30 jam < SLA 72 jam
            'category_id' => $nilai->id, 'sla_target_hours' => $nilai->sla_hours,
        ], $created);
        DB::table('comments')->insert([
            ['ticket_id' => $t3, 'user_id' => $staffAkd, 'is_internal_note' => false,
                'body' => 'Sudah kami teruskan ke dosen pengampu. Nilai akan diinput hari ini.', 'created_at' => $created->copy()->addHours(4)],
            ['ticket_id' => $t3, 'user_id' => $mahasiswa, 'is_internal_note' => false,
                'body' => 'Terima kasih, Bu.', 'created_at' => $created->copy()->addHours(5)],
        ]);
        $this->proof($t3, $staffAkd, 'bukti-nilai-terinput.pdf', $created->copy()->addHours(30));
        DB::table('ratings')->insert([
            'ticket_id' => $t3, 'user_id' => $mahasiswa, 'score' => 5,
            'comment' => 'Cepat ditangani, terima kasih.', 'created_at' => $created->copy()->addHours(31),
        ]);

        // ===== 7) SELESAI — alumni 2023, transkrip =====
        $trn = $cat('Transkrip Nilai');
        $created = $now->copy()->subDays(6);
        $t7 = $this->ticket([
            'faculty_id' => $fitk, 'title' => 'Nilai Statistika di transkrip tidak sesuai',
            'description' => 'Di KHS nilai saya A, tetapi di transkrip tertulis B.',
            'status' => 'selesai',
            'reporter_id' => $alumni2023, 'reporter_type' => 'alumni',
            'handler_id' => $staffAlm, 'assigned_at' => $created->copy()->addHours(5),
            'first_responded_at' => $created->copy()->addHours(6),
            'resolved_at' => $created->copy()->addHours(40), // 40 jam < SLA 72 jam -> patuh SLA
            'category_id' => $trn->id, 'sla_target_hours' => $trn->sla_hours,
        ], $created);
        $this->proof($t7, $staffAlm, 'transkrip-revisi.pdf', $created->copy()->addHours(40));
        DB::table('ratings')->insert([
            'ticket_id' => $t7, 'user_id' => $alumni2023, 'score' => 5,
            'comment' => 'Cepat dan jelas, terima kasih.', 'created_at' => $created->copy()->addHours(42),
        ]);

        // ===== 4) DITOLAK "Salah fakultas/kategori" — tombol Buat Ulang Tiket muncul =====
        $jadwal = $cat('Jadwal mata kuliah');
        $created = $now->copy()->subDays(4);
        $t4 = $this->ticket([
            'faculty_id' => $fitk, 'title' => 'Jadwal kuliah Akuntansi Syariah bentrok',
            'description' => 'Jadwal Akuntansi Syariah dan Ekonomi Mikro sama-sama hari Senin jam 08.00.',
            'status' => 'ditolak',
            'rejection_reason' => 'Salah fakultas/kategori: mata kuliah ini milik FEBI. Silakan buat ulang tiket ke FEBI.',
            'reporter_id' => $mahasiswa, 'reporter_type' => 'mahasiswa',
            'handler_id' => $staffAkd, 'assigned_at' => $created->copy()->addHours(2),
            'first_responded_at' => $created->copy()->addHours(2),
            'resolved_at' => $created->copy()->addHours(3),
            'category_id' => $jadwal->id, 'sla_target_hours' => $jadwal->sla_hours,
        ], $created);
        $this->attachment(['ticket_id' => $t4], 'jadwal-bentrok.png', $created);

        // ===== 6) DIPROSES — dosen, Wi-Fi; ada Ping dari admin =====
        $it = $cat('Akun, Jaringan & IT');
        $created = $now->copy()->subHours(30);
        $t6 = $this->ticket([
            'faculty_id' => $fitk, 'title' => 'Wi-Fi ruang dosen lantai 2 mati',
            'description' => 'Sejak Senin Wi-Fi di ruang dosen lantai 2 tidak bisa tersambung.',
            'status' => 'diproses',
            'reporter_id' => $dosen, 'reporter_type' => 'dosen',
            'handler_id' => $staffUmum, 'assigned_at' => $created->copy()->addHours(3),
            'first_responded_at' => $created->copy()->addHours(4),
            'category_id' => $it->id, 'sla_target_hours' => $it->sla_hours,
        ], $created);
        DB::table('comments')->insert([
            ['ticket_id' => $t6, 'user_id' => $staffUmum, 'is_internal_note' => false,
                'body' => 'Baik Pak, sudah kami teruskan ke teknisi jaringan.', 'created_at' => $created->copy()->addHours(4)],
            ['ticket_id' => $t6, 'user_id' => $staffUmum, 'is_internal_note' => true, // hanya staff & admin yang lihat
                'body' => 'Access point AP-L2-03 perlu diganti, menunggu barang dari pusat.', 'created_at' => $created->copy()->addHours(5)],
        ]);
        DB::table('ping_logs')->insert([ // admin menegur staff karena sudah lewat SLA 24 jam
            'ticket_id' => $t6, 'admin_id' => $adminFitk, 'staff_id' => $staffUmum,
            'message' => 'Mohon dipercepat, SLA sudah terlewati.', 'created_at' => $now,
        ]);
        DB::table('notifications')->insert([ // notifikasi dari Ping di atas
            'user_id' => $staffUmum, 'ticket_id' => $t6, 'type' => 'in_app',
            'subject' => 'Ping dari Admin FITK', 'message' => 'Mohon dipercepat, SLA sudah terlewati.',
            'is_read' => false, 'sent_at' => $now,
        ]);

        // ===== 2) DIPROSES — mahasiswa, proyektor; thread dua arah + catatan internal =====
        $fas = $cat('Fasilitas, Sarana & Prasarana');
        $created = $now->copy()->subHours(20);
        $t2 = $this->ticket([
            'faculty_id' => $fitk, 'title' => 'Proyektor ruang 3.04 tidak menyala saat kuliah',
            'description' => 'Proyektor di ruang 3.04 gedung FITK mati total sejak kemarin pagi. Kabel sudah dicek.',
            'status' => 'diproses',
            'reporter_id' => $mahasiswa, 'reporter_type' => 'mahasiswa',
            'handler_id' => $staffUmum, 'assigned_at' => $created->copy()->addHours(2),
            'first_responded_at' => $created->copy()->addHours(3),
            'category_id' => $fas->id, 'sla_target_hours' => $fas->sla_hours,
        ], $created);
        DB::table('comments')->insert([
            ['ticket_id' => $t2, 'user_id' => $staffUmum, 'is_internal_note' => false,
                'body' => 'Terima kasih laporannya. Apakah lampu indikator proyektor menyala oranye?', 'created_at' => $created->copy()->addHours(3)],
            ['ticket_id' => $t2, 'user_id' => $staffUmum, 'is_internal_note' => true,
                'body' => 'Kemungkinan lampu proyektor habis, cek stok di gudang sarpras.', 'created_at' => $created->copy()->addHours(3)->addMinutes(5)],
        ]);
        $reply = DB::table('comments')->insertGetId([
            'ticket_id' => $t2, 'user_id' => $mahasiswa, 'is_internal_note' => false,
            'body' => 'Iya, lampunya oranye berkedip. Saya lampirkan fotonya.', 'created_at' => $created->copy()->addHours(4),
        ]);
        $this->attachment(['comment_id' => $reply], 'foto-proyektor.png', $created->copy()->addHours(4));

        // ===== 5) MENUNGGU — Integritas Dosen (sensitif) =====
        $int = $cat('Integritas Dosen');
        $created = $now->copy()->subHours(5);
        $this->ticket([
            'faculty_id' => $fitk, 'title' => 'Dosen pengampu sering tidak hadir tanpa kabar',
            'description' => 'Sudah 4 pertemuan dosen tidak hadir tanpa pemberitahuan dan tanpa pengganti.',
            'status' => 'menunggu',
            'reporter_id' => $mahasiswa, 'reporter_type' => 'mahasiswa',
            'category_id' => $int->id, 'sla_target_hours' => $int->sla_hours,
        ], $created);

        // ===== 1) MENUNGGU — baru dibuat, belum diambil staff, ada lampiran =====
        $krs = $cat('KRS belum di-ACC'); // kategori LEAF (anak dari Sistem Akademik)
        $created = $now->copy()->subHours(2);
        $t1 = $this->ticket([
            'faculty_id' => $fitk, 'title' => 'KRS saya belum di-ACC Dosen PA',
            'description' => 'Sudah 3 hari KRS belum disetujui, padahal masa KRS berakhir lusa.',
            'status' => 'menunggu',
            'reporter_id' => $mahasiswa, 'reporter_type' => 'mahasiswa', // snapshot user_type
            'handler_id' => null, 'assigned_at' => null,
            'category_id' => $krs->id, 'sla_target_hours' => $krs->sla_hours, // snapshot SLA (24)
        ], $created);
        $this->attachment(['ticket_id' => $t1], 'screenshot-krs.png', $created); // lampiran form awal
    }

    /** Insert tiket dengan nomor TKT-YYYYMM-XXXX sesuai bulan created_at. */
    private function ticket(array $data, Carbon $created): int
    {
        $month = $created->format('Ym');
        $this->sequence[$month] = ($this->sequence[$month] ?? 0) + 1;

        return DB::table('tickets')->insertGetId($data + [
            'ticket_number' => 'TKT-'.$month.'-'.str_pad((string) $this->sequence[$month], 4, '0', STR_PAD_LEFT),
            'created_at' => $created,
            'updated_at' => $data['resolved_at'] ?? $data['first_responded_at'] ?? $created,
        ]);
    }

    /** Lampiran demo: berkas PNG/PDF asli ditulis ke disk privat, lalu barisnya dicatat. */
    private function attachment(array $owner, string $name, Carbon $at): void
    {
        [$path, $mime, $size] = $this->file('attachments/demo/'.$name);

        DB::table('attachments')->insert($owner + [
            'ticket_id' => null, 'comment_id' => null,
            'file_name' => $name, 'file_path' => $path, 'mime_type' => $mime, 'file_size' => $size, 'created_at' => $at,
        ]);
    }

    private function proof(int $ticketId, int $staffId, string $name, Carbon $at): void
    {
        [$path, $mime, $size] = $this->file('proofs/demo/'.$name);

        DB::table('resolution_proofs')->insert([
            'ticket_id' => $ticketId, 'uploaded_by' => $staffId,
            'file_name' => $name, 'file_path' => $path, 'mime_type' => $mime, 'file_size' => $size, 'created_at' => $at,
        ]);
    }

    /** @return array{0: string, 1: string, 2: int} path, mime, ukuran */
    private function file(string $path): array
    {
        $isPdf = str_ends_with($path, '.pdf');
        $content = $isPdf ? self::PDF : base64_decode(self::PNG);
        Storage::disk(AttachmentStorage::DISK)->put($path, $content);

        return [$path, $isPdf ? 'application/pdf' : 'image/png', strlen($content)];
    }
}
