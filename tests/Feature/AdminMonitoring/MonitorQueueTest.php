<?php

namespace Tests\Feature\AdminMonitoring;

use App\Enums\TicketStatus;
use App\Models\Comment;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Monitor Antrean (US-16): tiket aktif fakultas + penanda SLA + filter + detail HANYA BACA.
 */
class MonitorQueueTest extends AdminTestCase
{
    public function test_default_shows_active_tickets_oldest_first_with_sla_marks(): void
    {
        $this->ticket(['title' => 'Baru menunggu', 'created' => now()->subHour()]);
        $this->ticket(['title' => 'Lama menunggu', 'created' => now()->subHours(30)]);                 // KRS 24j -> merah
        $this->ticket(['title' => 'Hampir lewat', 'created' => now()->subHours(20)]);                  // sisa 4j < 25% -> kuning
        $this->ticket(['title' => 'Sedang diproses', 'status' => TicketStatus::Diproses, 'created' => now()->subHours(2)]);
        $this->ticket(['title' => 'Sudah selesai', 'status' => TicketStatus::Selesai, 'created' => now()->subDays(3)]);

        $this->actingAs($this->admin)->get('/admin/queue')
            ->assertOk()
            ->assertSeeInOrder(['Lama menunggu', 'Hampir lewat', 'Sedang diproses', 'Baru menunggu'])
            ->assertDontSee('Sudah selesai')
            ->assertSee('Melewati SLA')
            ->assertSee('Mendekati SLA')
            ->assertSee('Waktu menunggu')
            ->assertSee('1 hari 6 jam') // kolom "Waktu Menunggu" tiket merah
            ->assertSee('Ping / Ingatkan')
            ->assertDontSee('Ambil Tiket');
    }

    public function test_filters_status_category_staff_type_dates_search_and_sla(): void
    {
        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->ticket(['title' => 'KRS menunggu', 'created' => '2026-10-20 08:00']);
        $this->ticket(['title' => 'KRS lama', 'created' => '2026-10-10 08:00']);
        $this->ticket(['title' => 'Nilai diproses Rudi', 'status' => TicketStatus::Diproses, 'handler' => $this->akademik2, 'category' => $this->cat('Nilai belum muncul'), 'created' => '2026-10-19 08:00']);
        $this->ticket(['title' => 'Fasilitas selesai', 'status' => TicketStatus::Selesai, 'handler' => $this->umum, 'category' => $this->cat('Fasilitas, Sarana & Prasarana'), 'created' => '2026-10-15 08:00']);
        $dosen = User::factory()->dosen($this->fitk)->create();
        $this->ticket(['title' => 'Jadwal dosen', 'reporter' => $dosen, 'category' => $this->cat('Jadwal Mengajar'), 'created' => '2026-10-19 09:00']);

        $get = fn (array $q) => $this->actingAs($this->admin)->get('/admin/queue?'.http_build_query($q))->assertOk();

        $get(['status' => 'diproses'])->assertSee('Nilai diproses Rudi')->assertDontSee('KRS menunggu');
        $get(['status' => 'selesai'])->assertSee('Fasilitas selesai')->assertDontSee('KRS menunggu');
        $get(['status' => 'semua'])->assertSee('Fasilitas selesai')->assertSee('KRS menunggu');
        // Kelompok (mencakup semua Jenis Masalah-nya) vs Jenis Masalah tertentu.
        $get(['category' => $this->cat('Sistem Akademik & Perkuliahan')->id])->assertSee('KRS menunggu')->assertSee('Nilai diproses Rudi')->assertDontSee('Jadwal dosen');
        $get(['category' => $this->cat('Nilai belum muncul')->id])->assertSee('Nilai diproses Rudi')->assertDontSee('KRS menunggu');
        $get(['staff' => $this->akademik2->id])->assertSee('Nilai diproses Rudi')->assertDontSee('KRS menunggu');
        $get(['type' => 'dosen'])->assertSee('Jadwal dosen')->assertDontSee('KRS menunggu');
        $get(['from' => '2026-10-19', 'to' => '2026-10-19'])->assertSee('Jadwal dosen')->assertSee('Nilai diproses Rudi')->assertDontSee('KRS menunggu')->assertDontSee('KRS lama');
        $get(['sla' => 'overdue'])->assertSee('KRS lama')->assertDontSee('KRS menunggu');
        $get(['q' => 'lama'])->assertSee('KRS lama')->assertDontSee('KRS menunggu');
        // Kotak cari di top bar (tanpa status) mencari di semua status, termasuk tiket yang sudah ditutup.
        $get(['q' => 'Fasilitas'])->assertSee('Fasilitas selesai');
        $get(['status' => 'ngawur', 'from' => 'bukan-tanggal'])->assertSee('KRS menunggu'); // isian aneh diabaikan

        Carbon::setTestNow();
    }

    public function test_detail_is_read_only_and_shows_everything(): void
    {
        $sensitive = $this->ticket(['status' => TicketStatus::Selesai, 'handler' => $this->dekanat, 'category' => $this->cat('Integritas Dosen'), 'rating' => 4, 'title' => 'Aduan dosen']);
        $sensitive->resolutionProofs()->create(['file_name' => 'berita-acara.pdf', 'file_path' => 'proofs/x.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100, 'uploaded_by' => $this->dekanat->id]);
        Comment::create(['ticket_id' => $sensitive->id, 'user_id' => $this->dekanat->id, 'body' => 'Catatan rahasia staff', 'is_internal_note' => true]);
        Comment::create(['ticket_id' => $sensitive->id, 'user_id' => $this->dekanat->id, 'body' => 'Balasan publik staff', 'is_internal_note' => false]);

        $this->actingAs($this->admin)->get("/admin/tickets/{$sensitive->id}")
            ->assertOk()
            ->assertSee('Ahmad Mahasiswa')          // identitas pelapor kategori sensitif boleh dilihat Admin
            ->assertDontSee('Identitas dirahasiakan')
            ->assertSee('Catatan rahasia staff')    // catatan internal
            ->assertSee('Balasan publik staff')
            ->assertSee('berita-acara.pdf')         // bukti
            ->assertSee('4/5')                      // rating
            ->assertSee('Halaman ini hanya untuk memantau')
            ->assertDontSee('Kirim Ping');          // tiket ditutup -> tidak ada Ping
    }

    public function test_admin_can_download_proof_of_own_faculty(): void
    {
        $ticket = $this->ticket(['status' => TicketStatus::Selesai]);
        Storage::disk('local')->put('proofs/y.pdf', '%PDF-1.4');
        $proof = $ticket->resolutionProofs()->create(['file_name' => 'y.pdf', 'file_path' => 'proofs/y.pdf', 'mime_type' => 'application/pdf', 'file_size' => 8, 'uploaded_by' => $this->akademik->id]);

        $this->actingAs($this->admin)->get("/proofs/{$proof->id}")->assertOk();
        $this->actingAs($this->adminFebi)->get("/proofs/{$proof->id}")->assertForbidden();
    }

    public function test_sidebar_counts_idle_tickets(): void
    {
        $this->ticket(['created' => now()->subHours(30)]);
        $this->ticket(['created' => now()->subHour()]);

        $menu = collect(Navigation::for($this->admin))->keyBy('route');
        $this->assertSame(1, $menu['admin.queue']['count']);
        $this->assertSame('Laporan & Analitik', $menu['admin.reports.index']['label']);
    }
}
