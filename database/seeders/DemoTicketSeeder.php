<?php

namespace Database\Seeders;

use App\Services\Tickets\AttachmentStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 12 tiket contoh di FITK yang KONSISTEN dengan alur PRD 4.5, supaya setiap halaman M2 & M3 langsung berisi:
 *
 *  #   pelapor          kategori (leaf)                         status    yang ikut terisi
 *  --  ---------------  --------------------------------------  --------  -------------------------------------------
 *  1   Ahmad Mahasiswa  Sistem Akademik › KRS belum di-ACC      menunggu  lampiran form; antrean Staff Akademik (aman)
 *  2   Ahmad Mahasiswa  Fasilitas, Sarana & Prasarana           diproses  thread: staff ↔ mahasiswa + 1 catatan internal
 *  3   Ahmad Mahasiswa  Sistem Akademik › Nilai belum muncul    selesai   bukti penyelesaian (PDF), rating 5, sesuai SLA
 *  4   Ahmad Mahasiswa  Sistem Akademik › Jadwal mata kuliah    ditolak   alasan baku "Salah fakultas/kategori" -> bisa Buat Ulang
 *  5   Ahmad Mahasiswa  Integritas Dosen (SENSITIF)             menunggu  hanya di antrean staff Dekanat, identitas disamarkan
 *  6   Dr. Budi Dosen   Akun, Jaringan & IT                     diproses  thread, Ping admin, notifikasi; MELEWATI SLA (merah)
 *  7   Citra Alumni     Transkrip Nilai                         selesai   bukti penyelesaian, rating
 *  8   Dedi Alumni Lama Kesalahan Data PDDIKTI                  ditolak   alasan lain (tidak bisa Buat Ulang)
 *  9   Ahmad Mahasiswa  Keuangan & UKT                          selesai   bukti, BELUM dirating (coba beri rating), melewati SLA
 *  10  Ahmad Mahasiswa  Sistem Akademik › Nilai belum muncul    menunggu  antrean Staff Akademik: MELEWATI SLA (merah)
 *  11  Ahmad Mahasiswa  Sistem Akademik › Jadwal mata kuliah    diproses  Tiket Saya Staff Akademik: MENDEKATI SLA (kuning),
 *                                                                         belum ada balasan staff (first_responded_at kosong)
 *  12  Dr. Budi Dosen   Jadwal Mengajar                         menunggu  antrean Staff Akademik: MENDEKATI SLA (kuning)
 *
 * Tambahan M4 (laporan & monitoring Admin):
 *  13  Yusuf Alumni     Transkrip Nilai                         menunggu  MELEWATI SLA, sudah 2x di-Ping admin (staff alumni)
 *  +   Ping admin ke tiket #10 (Nilai Bahasa Arab, menunggu, merah) -> teguran belum dibaca di dashboard Staff Akademik
 *  +   ±90 tiket RIWAYAT (Selesai/Ditolak) selama 5 bulan terakhir di FITK & FEBI, milik 7 pelapor tambahan,
 *      dengan waktu ambil/selesai bervariasi (ada yang melewati SLA), rating 2–5, dan beberapa Ping lama.
 *      Dibuat acak tetapi SELALU SAMA setiap seeding (mt_srand dengan angka tetap), untuk grafik & laporan.
 *
 * Tambahan M5: notifikasi lonceng Ahmad untuk tiket #2 (diproses: sudah dibaca; balasan Staff: belum dibaca).
 *
 * Aturan yang dijaga (dicek DatabaseSeederTest): kategori selalu LEAF dan audiensnya cocok dengan jenis pelapor;
 * sla_target_hours & reporter_type = snapshot; handler punya spesialisasi kelompok tiket; status mengikuti PRD 4.5
 * (menunggu tanpa handler; diproses/selesai/ditolak punya handler + assigned_at; selesai punya bukti; ditolak punya
 * alasan; selesai/ditolak punya resolved_at); nomor TKT-YYYYMM-XXXX urut per bulan sesuai tanggal dibuat;
 * berkas demo benar-benar ada di disk privat.
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

    /** @var list<array> Ping demo yang ditulis SETELAH nomor tiket final (isi notifikasi memuat nomor tiket). */
    private array $pendingPings = [];

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

        // ===== 9) SELESAI — UKT, belum dirating (pelapor bisa mencoba memberi rating), melewati SLA =====
        $ukt = $cat('Keuangan & UKT');
        $created = $now->copy()->subDays(5);
        $t9 = $this->ticket([
            'faculty_id' => $fitk, 'title' => 'Pembayaran UKT belum terverifikasi',
            'description' => 'Saya sudah membayar UKT lewat bank tanggal 2, tetapi status di portal masih "belum bayar".',
            'status' => 'selesai',
            'reporter_id' => $mahasiswa, 'reporter_type' => 'mahasiswa',
            'handler_id' => $staffUmum, 'assigned_at' => $created->copy()->addHours(20),
            'first_responded_at' => $created->copy()->addHours(21),
            'resolved_at' => $created->copy()->addHours(80), // 80 jam > SLA 72 jam -> melewati SLA
            'category_id' => $ukt->id, 'sla_target_hours' => $ukt->sla_hours,
        ], $created);
        DB::table('comments')->insert([
            ['ticket_id' => $t9, 'user_id' => $staffUmum, 'is_internal_note' => false,
                'body' => 'Mohon kirim bukti transfer agar kami cocokkan dengan bagian keuangan.', 'created_at' => $created->copy()->addHours(21)],
            ['ticket_id' => $t9, 'user_id' => $staffUmum, 'is_internal_note' => true,
                'body' => 'Bank terlambat mengirim data rekonsiliasi, sudah dikonfirmasi ke keuangan rektorat.', 'created_at' => $created->copy()->addHours(60)],
        ]);
        $this->proof($t9, $staffUmum, 'status-ukt-terverifikasi.png', $created->copy()->addHours(80));

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

        // ===== 10) MENUNGGU — sudah 80 jam, SLA 72 jam -> merah "Melewati SLA" di antrean =====
        $created = $now->copy()->subHours(80);
        $this->ticket([
            'faculty_id' => $fitk, 'title' => 'Nilai Bahasa Arab belum keluar',
            'description' => 'Nilai Bahasa Arab II semester lalu belum muncul, padahal teman sekelas sudah.',
            'status' => 'menunggu',
            'reporter_id' => $mahasiswa, 'reporter_type' => 'mahasiswa',
            'category_id' => $nilai->id, 'sla_target_hours' => $nilai->sla_hours,
        ], $created);

        // ===== 11) DIPROSES — Staff Akademik, 40 jam dari SLA 48 -> kuning; belum dibalas staff =====
        $jadwalMk = $cat('Jadwal mata kuliah');
        $created = $now->copy()->subHours(40);
        $t11 = $this->ticket([
            'faculty_id' => $fitk, 'title' => 'Ruang kuliah Tafsir belum muncul di jadwal',
            'description' => 'Di portal, mata kuliah Tafsir Tarbawi kelas B belum ada ruangannya. Kuliah dimulai Senin.',
            'status' => 'diproses',
            'reporter_id' => $mahasiswa, 'reporter_type' => 'mahasiswa',
            'handler_id' => $staffAkd, 'assigned_at' => $created->copy()->addHours(6),
            'first_responded_at' => null, // staff belum membalas: balasan publik pertamanya akan mengisi kolom ini
            'category_id' => $jadwalMk->id, 'sla_target_hours' => $jadwalMk->sla_hours,
        ], $created);
        DB::table('comments')->insert([
            'ticket_id' => $t11, 'user_id' => $mahasiswa, 'is_internal_note' => false,
            'body' => 'Tambahan: kelas A sudah dapat ruang 2.01.', 'created_at' => $created->copy()->addHours(8),
        ]);

        // ===== 12) MENUNGGU — dosen, Jadwal Mengajar, 38 jam dari SLA 48 -> kuning "Mendekati SLA" =====
        $mengajar = $cat('Jadwal Mengajar');
        $created = $now->copy()->subHours(38);
        $this->ticket([
            'faculty_id' => $fitk, 'title' => 'Jadwal mengajar Kamis bentrok dengan rapat prodi',
            'description' => 'Jadwal mengajar Kamis 10.00 bentrok dengan rapat rutin prodi. Mohon dipindah ke sesi siang.',
            'status' => 'menunggu',
            'reporter_id' => $dosen, 'reporter_type' => 'dosen',
            'category_id' => $mengajar->id, 'sla_target_hours' => $mengajar->sla_hours,
        ], $created);

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
        // Admin menegur staff penangan karena sudah lewat SLA 24 jam (+ notifikasi in-app).
        $this->ping($t6, $adminFitk, [$staffUmum], 'Mohon dipercepat, SLA sudah terlewati.', $now->copy()->subMinutes(30), false);

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

        // ===== 13) MENUNGGU — alumni, Transkrip Nilai, sudah 4 hari (SLA 72 jam) -> merah, 2x di-Ping (M4) =====
        $yusuf = $user('yusuf.alumni2022@uinssc.test');
        $created = $now->copy()->subHours(100);
        $t13 = $this->ticket([
            'faculty_id' => $fitk, 'title' => 'Legalisir transkrip untuk melamar CPNS belum diproses',
            'description' => 'Saya mengajukan legalisir transkrip 5 lembar untuk berkas CPNS. Batas unggah berkas minggu depan.',
            'status' => 'menunggu',
            'reporter_id' => $yusuf, 'reporter_type' => 'alumni',
            'category_id' => $trn->id, 'sla_target_hours' => $trn->sla_hours,
        ], $created);
        $this->ping($t13, $adminFitk, [$staffAlm], 'Tiket legalisir ini sudah melewati SLA, mohon diambil hari ini.', $now->copy()->subHours(26), true);
        $this->ping($t13, $adminFitk, [$staffAlm], 'Pengingat kedua: pelapor butuh untuk berkas CPNS.', $now->copy()->subHours(3), false);

        // Ping admin ke tiket #10 yang masih menunggu & merah -> teguran belum dibaca untuk Staff Akademik.
        $t10 = DB::table('tickets')->where('title', 'Nilai Bahasa Arab belum keluar')->value('id');
        $this->ping($t10, $adminFitk, [$staffAkd], 'Nilai Bahasa Arab sudah lewat 3 hari, mohon segera diambil.', $now->copy()->subHours(1), false);

        // ===== Riwayat beberapa bulan untuk grafik & laporan Admin (M4) =====
        $this->history($fitk, 'FITK', $now, $adminFitk);
        $this->history(DB::table('faculties')->where('code', 'FEBI')->value('id'), 'FEBI', $now,
            DB::table('users')->where('email', 'admin.febi@uinssc.test')->value('id'));

        $this->renumber();

        foreach ($this->pendingPings as $ping) {
            $this->writePing(...$ping);
        }

        // ===== M5: notifikasi lonceng pelapor (sama seperti TicketNotifier) untuk tiket #2 milik Ahmad =====
        // "diproses" sudah dibaca, balasan Staff BELUM dibaca -> kotak "Notifikasi baru" + badge "Balasan baru" di dashboard.
        $number = DB::table('tickets')->where('id', $t2)->value('ticket_number');
        $assignedAt = Carbon::parse(DB::table('tickets')->where('id', $t2)->value('assigned_at'));
        $staffReply = DB::table('comments')->where('ticket_id', $t2)->where('user_id', $staffUmum)->where('is_internal_note', false)->first(['body', 'created_at']);
        DB::table('notifications')->insert([
            ['user_id' => $mahasiswa, 'ticket_id' => $t2, 'type' => 'in_app', 'subject' => "Tiket {$number} sedang diproses",
                'message' => 'Diambil oleh Staff Umum FITK.', 'is_read' => true, 'sent_at' => $assignedAt, 'read_at' => $assignedAt->copy()->addMinutes(30)],
            ['user_id' => $mahasiswa, 'ticket_id' => $t2, 'type' => 'in_app', 'subject' => "Balasan baru dari Staff: {$number}",
                'message' => $staffReply->body, 'is_read' => false, 'sent_at' => $staffReply->created_at, 'read_at' => null],
        ]);
    }

    /**
     * Tiket RIWAYAT (Selesai/Ditolak) selama ±150 hari terakhir, untuk grafik tren, laporan, dan ekspor (M4).
     * Acak tetapi selalu sama (mt_srand). Setiap tiket mengikuti alur PRD 4.5 dan aturan yang dicek DatabaseSeederTest:
     * kategori leaf + audiens cocok, handler punya spesialisasi kelompoknya, Selesai punya bukti, Ditolak punya alasan.
     */
    private function history(int $facultyId, string $code, Carbon $now, int $adminId): void
    {
        mt_srand($code === 'FITK' ? 2026 : 2027);

        $user = fn (string $email) => DB::table('users')->where('email', $email)->value('id');
        $reporters = [
            'mahasiswa' => array_map($user, ['siti.aminah@uinssc.test', 'rizky.pratama@uinssc.test', 'nurul.hidayah@uinssc.test', 'fajar.ramadhan@uinssc.test']),
            'dosen' => array_map($user, ['hendra.saputra@uinssc.test', 'lina.marlina@uinssc.test']),
            'alumni' => array_map($user, ['yusuf.alumni2022@uinssc.test']),
        ];

        // [kategori leaf, jenis pelapor yang boleh, email staff penangan, bobot (seberapa sering), contoh judul]
        $templates = $code === 'FITK' ? [
            ['Nilai belum muncul', ['mahasiswa'], 'staff.akademik.fitk@uinssc.test', 6, ['Nilai {mk} belum muncul di portal', 'Nilai UAS {mk} masih kosong']],
            ['KRS belum di-ACC', ['mahasiswa'], 'staff.akademik.fitk@uinssc.test', 5, ['KRS belum disetujui Dosen PA', 'KRS ditolak sistem padahal SKS cukup']],
            ['Jadwal mata kuliah', ['mahasiswa'], 'staff.akademik.fitk@uinssc.test', 3, ['Jadwal {mk} bentrok', 'Ruang kuliah {mk} belum ada']],
            ['Pelayanan Administrasi & Birokrasi', ['mahasiswa', 'dosen'], 'staff.umum.fitk@uinssc.test', 4, ['Surat keterangan aktif kuliah belum terbit', 'Legalisir ijazah lambat', 'Surat tugas belum ditandatangani']],
            ['Keuangan & UKT', ['mahasiswa'], 'staff.umum.fitk@uinssc.test', 4, ['UKT sudah dibayar tapi belum terverifikasi', 'Pengajuan keringanan UKT belum dijawab']],
            ['Fasilitas, Sarana & Prasarana', ['mahasiswa', 'dosen'], 'staff.umum.fitk@uinssc.test', 4, ['AC ruang {ruang} mati', 'Proyektor ruang {ruang} rusak', 'Toilet lantai 2 tidak ada air']],
            ['Akun, Jaringan & IT', ['mahasiswa', 'dosen'], 'staff.umum.fitk@uinssc.test', 5, ['Tidak bisa login portal akademik', 'Wi-Fi gedung FITK putus-putus', 'Email kampus tidak bisa menerima pesan']],
            ['Jadwal Mengajar', ['dosen'], 'staff.akademik.fitk@uinssc.test', 2, ['Jadwal mengajar {mk} bentrok', 'Perubahan ruang mengajar belum diumumkan']],
            ['Peminjaman ruang microteaching', ['mahasiswa', 'dosen'], 'staff.umum.fitk@uinssc.test', 2, ['Peminjaman ruang microteaching belum dikonfirmasi']],
            ['Transkrip Nilai', ['alumni'], 'staff.alumni.fitk@uinssc.test', 2, ['Permohonan legalisir transkrip', 'Nilai {mk} di transkrip tidak sesuai KHS']],
            ['Kesalahan Data PDDIKTI', ['alumni'], 'staff.alumni.fitk@uinssc.test', 1, ['Tanggal lahir di PDDIKTI salah']],
            ['Integritas Dosen', ['mahasiswa'], 'staff.dekanat.fitk@uinssc.test', 1, ['Dosen {mk} sering terlambat tanpa kabar']],
        ] : [
            ['Pelayanan Administrasi & Birokrasi', ['mahasiswa', 'dosen'], 'staff.umum.febi@uinssc.test', 3, ['Surat rekomendasi beasiswa belum terbit', 'Legalisir ijazah lambat']],
            ['Akun, Jaringan & IT', ['mahasiswa', 'dosen'], 'staff.umum.febi@uinssc.test', 3, ['Tidak bisa login SSO dari lab FEBI', 'Wi-Fi gedung FEBI mati']],
        ];
        $total = $code === 'FITK' ? 80 : 14;

        $mk = ['Statistika', 'Ilmu Pendidikan', 'Bahasa Arab', 'Tafsir Tarbawi', 'Psikologi Belajar', 'Metodologi Penelitian'];
        $ruang = ['2.01', '2.05', '3.02', '3.07', '4.01'];
        $reasons = [
            'Salah fakultas/kategori: layanan ini ditangani oleh Biro Akademik Rektorat.',
            'Tiket ganda (sudah dilaporkan di tiket lain): sudah dilaporkan di tiket sebelumnya.',
            'Data pelapor tidak valid: NIM tidak ditemukan di data akademik.',
            'Di luar layanan helpdesk fakultas: silakan hubungi bank penerima pembayaran.',
        ];

        $weighted = [];
        foreach ($templates as $template) {
            $weighted = array_merge($weighted, array_fill(0, $template[3], $template));
        }

        for ($i = 0; $i < $total; $i++) {
            [$leafName, $types, $staffEmail, , $titles] = $weighted[mt_rand(0, count($weighted) - 1)];
            $leaf = DB::table('categories')->where('faculty_id', $facultyId)->where('name', $leafName)->first(['id', 'sla_hours']);
            $type = $types[mt_rand(0, count($types) - 1)];
            $reporter = $reporters[$type][mt_rand(0, count($reporters[$type]) - 1)];
            $staffId = $user($staffEmail);

            // Lebih banyak tiket di bulan-bulan terakhir: hari ke belakang = 2..150, condong ke yang baru.
            $daysAgo = 2 + (int) floor(148 * (mt_rand(0, 1000) / 1000) ** 1.4);
            $created = $now->copy()->subDays($daysAgo)->setTime(mt_rand(7, 16), mt_rand(0, 59));
            $sla = (int) $leaf->sla_hours;

            // Lama di antrean: biasanya cepat, kadang lama (bahan Time-to-Claim & Claim Rate).
            $claimMinutes = mt_rand(1, 10) <= 8 ? mt_rand(15, 6 * 60) : mt_rand(8 * 60, 40 * 60);
            $assigned = $created->copy()->addMinutes($claimMinutes);
            $responded = $assigned->copy()->addMinutes(mt_rand(10, 180));

            $rejected = mt_rand(1, 100) <= 12;
            // ±80% selesai dalam SLA, sisanya terlambat 10–60% dari target.
            $resolveHours = mt_rand(1, 100) <= 80 ? mt_rand(20, 95) / 100 * $sla : $sla * mt_rand(110, 160) / 100;
            $resolved = $created->copy()->addMinutes((int) round($resolveHours * 60));
            if ($resolved->lessThanOrEqualTo($responded)) {
                $resolved = $responded->copy()->addMinutes(30);
            }
            if ($resolved->greaterThan($now->copy()->subHour())) {
                continue; // belum "selesai" di masa lalu -> lewati, data riwayat harus sudah ditutup
            }

            $title = str_replace(['{mk}', '{ruang}'], [$mk[mt_rand(0, count($mk) - 1)], $ruang[mt_rand(0, count($ruang) - 1)]], $titles[mt_rand(0, count($titles) - 1)]);
            $ticketId = $this->ticket([
                'faculty_id' => $facultyId, 'title' => $title,
                'description' => 'Data riwayat contoh untuk laporan: '.$title.'.',
                'status' => $rejected ? 'ditolak' : 'selesai',
                'rejection_reason' => $rejected ? $reasons[mt_rand(0, count($reasons) - 1)] : null,
                'reporter_id' => $reporter, 'reporter_type' => $type,
                'handler_id' => $staffId, 'assigned_at' => $assigned,
                'first_responded_at' => $responded, 'resolved_at' => $resolved,
                'category_id' => $leaf->id, 'sla_target_hours' => $sla,
            ], $created);

            DB::table('comments')->insert([
                'ticket_id' => $ticketId, 'user_id' => $staffId, 'is_internal_note' => false,
                'body' => $rejected ? 'Mohon maaf, tiket ini tidak dapat kami proses. Alasan ada di detail tiket.' : 'Baik, sedang kami tindak lanjuti.',
                'created_at' => $responded,
            ]);

            if (! $rejected) {
                $this->proof($ticketId, $staffId, 'bukti-'.($i + 1).'.'.(mt_rand(0, 1) ? 'pdf' : 'png'), $resolved);
                if (mt_rand(1, 100) <= 65) { // tidak semua pelapor memberi rating
                    $score = [2, 3, 4, 4, 4, 5, 5, 5, 5, 3][mt_rand(0, 9)];
                    DB::table('ratings')->insert([
                        'ticket_id' => $ticketId, 'user_id' => $reporter, 'score' => $score,
                        'comment' => $score >= 4 ? 'Terima kasih, cepat ditangani.' : ($score === 3 ? 'Cukup, tapi agak lama.' : 'Terlalu lama menunggu.'),
                        'created_at' => $resolved->copy()->addHours(mt_rand(1, 20))->min($now),
                    ]);
                }
            }

            // Tiket yang lama di antrean (> 75% SLA) pernah di-Ping admin sebelum diambil (sudah dibaca).
            if ($claimMinutes > $sla * 60 * 0.75) {
                $this->ping($ticketId, $adminId, [$staffId], 'Tiket ini sudah lama menunggu, mohon segera diambil.', $created->copy()->addMinutes((int) ($claimMinutes * 0.9)), true);
            }
        }
    }

    /**
     * Ping demo: satu baris ping_logs + satu notifikasi in-app per staff (sama seperti PingService).
     *
     * @param  list<int>  $staffIds
     */
    private function ping(int $ticketId, int $adminId, array $staffIds, string $message, Carbon $at, bool $read): void
    {
        $this->pendingPings[] = [$ticketId, $adminId, $staffIds, $message, $at, $read];
    }

    private function writePing(int $ticketId, int $adminId, array $staffIds, string $message, Carbon $at, bool $read): void
    {
        $ticket = DB::table('tickets')->where('id', $ticketId)->first(['ticket_number', 'title', 'faculty_id', 'sla_target_hours']);
        $code = DB::table('faculties')->where('id', $ticket->faculty_id)->value('code');

        foreach ($staffIds as $staffId) {
            DB::table('ping_logs')->insert([
                'ticket_id' => $ticketId, 'admin_id' => $adminId, 'staff_id' => $staffId, 'message' => $message, 'created_at' => $at,
            ]);
            DB::table('notifications')->insert([
                'user_id' => $staffId, 'ticket_id' => $ticketId, 'type' => 'in_app',
                'subject' => "Ping dari Admin {$code}: {$ticket->ticket_number}",
                'message' => "{$ticket->ticket_number} — {$ticket->title}\nPesan Admin: {$message}",
                'is_read' => $read, 'sent_at' => $at, 'read_at' => $read ? $at->copy()->addHour() : null,
            ]);
        }
    }

    /**
     * Nomor tiket TKT-YYYYMM-XXXX diurutkan ulang per bulan sesuai created_at, karena tiket riwayat dibuat
     * setelah tiket contoh utama. Dua langkah (nomor sementara dulu) supaya kolom UNIQUE tidak bentrok.
     */
    private function renumber(): void
    {
        $tickets = DB::table('tickets')->orderBy('created_at')->orderBy('id')->get(['id', 'created_at']);

        foreach ($tickets as $ticket) {
            DB::table('tickets')->where('id', $ticket->id)->update(['ticket_number' => 'TMP-'.$ticket->id]);
        }

        $sequence = [];
        foreach ($tickets as $ticket) {
            $month = Carbon::parse($ticket->created_at)->format('Ym');
            $sequence[$month] = ($sequence[$month] ?? 0) + 1;
            DB::table('tickets')->where('id', $ticket->id)->update([
                'ticket_number' => 'TKT-'.$month.'-'.str_pad((string) $sequence[$month], 4, '0', STR_PAD_LEFT),
            ]);
        }
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
