<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 4 tiket contoh di FITK, satu untuk setiap status, supaya kamu bisa lihat
 * kolom mana yang terisi / kosong di tiap tahap:
 *
 *  status    | handler_id | assigned_at | first_responded_at | resolved_at | tabel lain yang ikut terisi
 *  --------- | ---------- | ----------- | ------------------ | ----------- | ---------------------------
 *  menunggu  |  NULL      |  NULL       |  NULL              |  NULL       | attachments (lampiran form)
 *  diproses  |  terisi    |  terisi     |  terisi            |  NULL       | comments, ping_logs, notifications
 *  selesai   |  terisi    |  terisi     |  terisi            |  terisi     | resolution_proofs, ratings
 *  ditolak   |  terisi    |  terisi     |  terisi            |  terisi     | rejection_reason diisi
 *
 * HANYA untuk development. Jangan dijalankan di production.
 */
class DemoTicketSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('tickets')->exists()) {
            return; // sudah ada tiket -> jangan bikin dobel
        }

        $fitk = DB::table('faculties')->where('code', 'FITK')->value('id');
        $user = fn (string $email) => DB::table('users')->where('email', $email)->value('id');
        $cat  = fn (string $name) => DB::table('categories')
            ->where('faculty_id', $fitk)->where('name', $name)->first(['id', 'sla_hours']);

        $mahasiswa  = $user('mahasiswa@uinssc.test');
        $dosen      = $user('dosen@uinssc.test');
        $alumni2023 = $user('alumni2023@uinssc.test');
        $alumni2019 = $user('dedi.alumni2019@example.com');
        $staffAkd   = $user('staff.akademik.fitk@uinssc.test');
        $staffUmum  = $user('staff.umum.fitk@uinssc.test');
        $staffAlm   = $user('staff.alumni.fitk@uinssc.test');
        $adminFitk  = $user('admin.fitk@uinssc.test');

        $now = now();
        $no  = fn (int $i) => 'TKT-' . $now->format('Ym') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);

        // ===== 1) MENUNGGU — baru dibuat, belum diambil staff =====
        $krs = $cat('KRS belum di-ACC'); // kategori LEAF (anak dari Sistem Akademik)
        $t1 = DB::table('tickets')->insertGetId([
            'ticket_number' => $no(1), 'faculty_id' => $fitk,
            'title' => 'KRS saya belum di-ACC Dosen PA',
            'description' => 'Sudah 3 hari KRS belum disetujui, padahal masa KRS berakhir lusa.',
            'status' => 'menunggu',
            'reporter_id' => $mahasiswa, 'reporter_type' => 'mahasiswa', // snapshot user_type
            'handler_id' => null, 'assigned_at' => null,
            'category_id' => $krs->id, 'sla_target_hours' => $krs->sla_hours, // snapshot SLA (24)
            'created_at' => $now->copy()->subHours(2), 'updated_at' => $now->copy()->subHours(2),
        ]);
        DB::table('attachments')->insert([ // lampiran form awal -> ticket_id terisi, comment_id NULL
            'ticket_id' => $t1, 'comment_id' => null,
            'file_name' => 'screenshot-krs.png', 'file_path' => 'attachments/demo/screenshot-krs.png',
            'mime_type' => 'image/png', 'file_size' => 245000, 'created_at' => $now->copy()->subHours(2),
        ]);

        // ===== 2) DIPROSES — sudah diambil staff, sedang dikerjakan =====
        $it = $cat('Akun, Jaringan & IT');
        $created = $now->copy()->subHours(20);
        $t2 = DB::table('tickets')->insertGetId([
            'ticket_number' => $no(2), 'faculty_id' => $fitk,
            'title' => 'Wi-Fi ruang dosen lantai 2 mati',
            'description' => 'Sejak Senin Wi-Fi di ruang dosen lantai 2 tidak bisa tersambung.',
            'status' => 'diproses',
            'reporter_id' => $dosen, 'reporter_type' => 'dosen',
            'handler_id' => $staffUmum, 'assigned_at' => $created->copy()->addHours(3),
            'first_responded_at' => $created->copy()->addHours(4),
            'category_id' => $it->id, 'sla_target_hours' => $it->sla_hours,
            'created_at' => $created, 'updated_at' => $created->copy()->addHours(4),
        ]);
        DB::table('comments')->insert([
            ['ticket_id' => $t2, 'user_id' => $staffUmum, 'is_internal_note' => false,
             'body' => 'Baik Pak, sudah kami teruskan ke teknisi jaringan.', 'created_at' => $created->copy()->addHours(4)],
            ['ticket_id' => $t2, 'user_id' => $staffUmum, 'is_internal_note' => true, // hanya staff & admin yang lihat
             'body' => 'Access point AP-L2-03 perlu diganti, menunggu barang dari pusat.', 'created_at' => $created->copy()->addHours(5)],
        ]);
        DB::table('ping_logs')->insert([ // admin menegur staff karena hampir lewat SLA 24 jam
            'ticket_id' => $t2, 'admin_id' => $adminFitk, 'staff_id' => $staffUmum,
            'message' => 'Mohon dipercepat, SLA tinggal 4 jam.', 'created_at' => $now,
        ]);
        DB::table('notifications')->insert([ // notifikasi dari Ping di atas
            'user_id' => $staffUmum, 'ticket_id' => $t2, 'type' => 'in_app',
            'subject' => 'Ping dari Admin FITK', 'message' => 'Mohon dipercepat, SLA tinggal 4 jam.',
            'is_read' => false, 'sent_at' => $now,
        ]);

        // ===== 3) SELESAI — wajib ada bukti penyelesaian, lalu pelapor memberi rating =====
        $trn = $cat('Transkrip Nilai');
        $created = $now->copy()->subDays(3);
        $t3 = DB::table('tickets')->insertGetId([
            'ticket_number' => $no(3), 'faculty_id' => $fitk,
            'title' => 'Nilai Statistika di transkrip tidak sesuai',
            'description' => 'Di KHS nilai saya A, tetapi di transkrip tertulis B.',
            'status' => 'selesai',
            'reporter_id' => $alumni2023, 'reporter_type' => 'alumni',
            'handler_id' => $staffAlm, 'assigned_at' => $created->copy()->addHours(5),
            'first_responded_at' => $created->copy()->addHours(6),
            'resolved_at' => $created->copy()->addHours(40), // 40 jam < SLA 72 jam -> patuh SLA
            'category_id' => $trn->id, 'sla_target_hours' => $trn->sla_hours,
            'created_at' => $created, 'updated_at' => $created->copy()->addHours(40),
        ]);
        DB::table('resolution_proofs')->insert([
            'ticket_id' => $t3, 'uploaded_by' => $staffAlm,
            'file_name' => 'transkrip-revisi.pdf', 'file_path' => 'proofs/demo/transkrip-revisi.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 512000, 'created_at' => $created->copy()->addHours(40),
        ]);
        DB::table('ratings')->insert([
            'ticket_id' => $t3, 'user_id' => $alumni2023, 'score' => 5,
            'comment' => 'Cepat dan jelas, terima kasih.', 'created_at' => $created->copy()->addHours(42),
        ]);

        // ===== 4) DITOLAK — tidak bisa langsung dari menunggu, jadi harus diambil staff dulu =====
        $pdd = $cat('Kesalahan Data PDDIKTI');
        $created = $now->copy()->subDays(5);
        DB::table('tickets')->insert([
            'ticket_number' => $no(4), 'faculty_id' => $fitk,
            'title' => 'Nama saya salah di PDDIKTI',
            'description' => 'Nama di PDDIKTI tertulis "Dedi Alumi", seharusnya "Dedi Alumni".',
            'status' => 'ditolak',
            'rejection_reason' => 'NIM tidak ditemukan di arsip akademik FITK. Silakan cek kembali fakultas asal.',
            'reporter_id' => $alumni2019, 'reporter_type' => 'alumni',
            'handler_id' => $staffAlm, 'assigned_at' => $created->copy()->addHours(8),
            'first_responded_at' => $created->copy()->addHours(9),
            'resolved_at' => $created->copy()->addHours(30), // ditolak juga mengisi resolved_at (waktu ditutup)
            'category_id' => $pdd->id, 'sla_target_hours' => $pdd->sla_hours,
            'created_at' => $created, 'updated_at' => $created->copy()->addHours(30),
        ]);
    }
}
