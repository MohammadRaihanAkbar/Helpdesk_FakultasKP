<?php

namespace Tests\Feature\AdminMonitoring;

use App\Enums\TicketStatus;
use App\Events\TicketPinged;
use App\Models\Notification;
use App\Models\PingLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

/**
 * Ping / Ingatkan Staff (US-14, PRD 9.3 POST /tickets/:id/ping, tabel ping_logs).
 */
class PingTest extends AdminTestCase
{
    public function test_waiting_ticket_pings_all_active_staff_of_the_group(): void
    {
        $ticket = $this->ticket(['created' => now()->subHours(30)]); // KRS, SLA 24 jam -> menganggur

        $response = $this->actingAs($this->admin)
            ->postJson("/admin/tickets/{$ticket->id}/ping", ['message' => 'Mohon segera ditindaklanjuti, tiket ini sudah menunggu.'])
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['ticket_id', 'pinged_staff' => [['id', 'name']], 'pinged_at', 'ping_log_ids'], 'message'])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ticket_id', $ticket->id)
            ->assertJsonPath('message', 'Notifikasi teguran berhasil dikirim ke 2 staff terkait.');

        // Penerima: Sari & Rudi (Sistem Akademik, aktif, FITK). BUKAN Nina (nonaktif), Umar (kelompok lain), Fajar (FEBI).
        $ids = collect($response->json('data.pinged_staff'))->pluck('id')->sort()->values()->all();
        $this->assertSame(collect([$this->akademik->id, $this->akademik2->id])->sort()->values()->all(), $ids);

        // Satu baris ping_logs per staff + satu notifikasi in-app per staff.
        $this->assertSame(2, PingLog::count());
        $this->assertEqualsCanonicalizing($response->json('data.ping_log_ids'), PingLog::pluck('id')->all());
        $log = PingLog::where('staff_id', $this->akademik->id)->first();
        $this->assertSame([$ticket->id, $this->admin->id, 'Mohon segera ditindaklanjuti, tiket ini sudah menunggu.'], [$log->ticket_id, $log->admin_id, $log->message]);

        $notification = Notification::where('user_id', $this->akademik->id)->first();
        $this->assertSame('in_app', $notification->type->value);
        $this->assertFalse($notification->is_read);
        $this->assertStringContainsString($ticket->ticket_number, $notification->subject);
        // Isi PRD US-14: nomor, judul, kategori, lama menunggu, pesan admin.
        foreach ([$ticket->ticket_number, $ticket->title, 'Sistem Akademik & Perkuliahan › KRS belum di-ACC', 'Menunggu di antrean selama 1 hari 6 jam', 'Pesan Admin: Mohon segera'] as $text) {
            $this->assertStringContainsString($text, $notification->message);
        }
        $this->assertSame(0, Notification::whereIn('user_id', [$this->nonaktif->id, $this->umum->id, $this->staffFebi->id])->count());
    }

    public function test_in_progress_ticket_pings_only_the_handler(): void
    {
        $ticket = $this->ticket(['status' => TicketStatus::Diproses, 'handler' => $this->akademik2]);

        $this->actingAs($this->admin)->postJson("/admin/tickets/{$ticket->id}/ping")
            ->assertOk()
            ->assertJsonPath('data.pinged_staff', [['id' => $this->akademik2->id, 'name' => 'Rudi Akademik']]);

        $this->assertSame([$this->akademik2->id], PingLog::pluck('staff_id')->all());
        $this->assertNull(PingLog::first()->message); // pesan opsional
        $this->assertStringContainsString('Pesan Admin: Mohon tiket ini segera ditindaklanjuti.', Notification::first()->message);
    }

    public function test_group_without_active_staff_returns_422_with_prd_message(): void
    {
        $ticket = $this->ticket(['category' => $this->cat('Keuangan & UKT')]); // tidak ada staff aktif di kelompok ini

        $this->actingAs($this->admin)->postJson("/admin/tickets/{$ticket->id}/ping")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'NO_ACTIVE_STAFF')
            ->assertJsonPath('error.message', 'Belum ada Staff untuk kelompok ini (tetapkan spesialisasi terlebih dahulu).');

        // Di Monitor Antrean tombolnya nonaktif dengan keterangan yang sama.
        $this->actingAs($this->admin)->get('/admin/queue')
            ->assertSee('Belum ada Staff untuk kelompok ini (tetapkan spesialisasi terlebih dahulu).');
        $this->assertDatabaseCount('ping_logs', 0);
    }

    public function test_inactive_handler_and_closed_ticket_cannot_be_pinged(): void
    {
        $orphan = $this->ticket(['status' => TicketStatus::Diproses, 'handler' => $this->nonaktif]);
        $closed = $this->ticket(['status' => TicketStatus::Selesai]);

        $this->actingAs($this->admin)->postJson("/admin/tickets/{$orphan->id}/ping")->assertStatus(422)->assertJsonPath('error.code', 'NO_ACTIVE_STAFF');
        $this->actingAs($this->admin)->postJson("/admin/tickets/{$closed->id}/ping")->assertStatus(409)->assertJsonPath('error.code', 'TICKET_CLOSED');
        $this->assertDatabaseCount('ping_logs', 0);
    }

    public function test_double_click_is_held_but_repeated_pings_are_allowed(): void
    {
        Carbon::setTestNow('2026-10-20 09:00:00');
        $ticket = $this->ticket(['created' => '2026-10-18 09:00']);

        $this->actingAs($this->admin)->postJson("/admin/tickets/{$ticket->id}/ping")->assertOk();
        $this->actingAs($this->admin)->postJson("/admin/tickets/{$ticket->id}/ping")->assertStatus(429)->assertJsonPath('error.code', 'PING_TOO_SOON');

        Carbon::setTestNow('2026-10-20 09:00:31'); // lewat jeda 30 detik -> boleh Ping lagi (PRD: tidak ada batas jumlah)
        $this->actingAs($this->admin)->postJson("/admin/tickets/{$ticket->id}/ping")->assertOk();

        $this->assertSame(4, PingLog::count()); // 2 aksi x 2 staff
        Carbon::setTestNow();
    }

    public function test_message_is_optional_but_limited(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->admin)->postJson("/admin/tickets/{$ticket->id}/ping", ['message' => str_repeat('a', 501)])
            ->assertStatus(422)->assertJsonValidationErrors('message');
        $this->assertDatabaseCount('ping_logs', 0);
    }

    public function test_browser_form_redirects_back_with_status_and_dispatches_event(): void
    {
        Event::fake([TicketPinged::class]);
        $ticket = $this->ticket(['status' => TicketStatus::Diproses]);

        $this->actingAs($this->admin)->from('/admin/queue')
            ->post("/admin/tickets/{$ticket->id}/ping", ['message' => 'Tolong dicek'])
            ->assertRedirect('/admin/queue')
            ->assertSessionHas('status', "Ping untuk {$ticket->ticket_number} terkirim ke 1 staff: Sari Akademik.");

        // Event untuk email teguran (M5) ikut terkirim, membawa penerima & log.
        Event::assertDispatched(TicketPinged::class, fn (TicketPinged $event) => $event->ticket->is($ticket)
            && $event->staff->pluck('id')->all() === [$this->akademik->id] && $event->logs->count() === 1 && $event->message === 'Tolong dicek');
    }

    public function test_staff_sees_ping_in_dashboard_notifications_and_timeline(): void
    {
        $ticket = $this->ticket(['status' => TicketStatus::Diproses, 'title' => 'Tiket yang ditegur']);
        $this->actingAs($this->admin)->post("/admin/tickets/{$ticket->id}/ping", ['message' => 'Segera selesaikan ya']);

        // Dashboard staff: kotak "Teguran dari Admin" + angka di lonceng.
        $this->actingAs($this->akademik)->get('/staff/dashboard')
            ->assertOk()
            ->assertSee('Teguran dari Admin (1 belum dibaca)')
            ->assertSee('Segera selesaikan ya')
            ->assertSee('Notifikasi (1 belum dibaca)');

        // Halaman notifikasi.
        $notification = Notification::first();
        $this->actingAs($this->akademik)->get('/notifications')->assertOk()->assertSee($notification->subject)->assertSee('Baru');

        // Klik notifikasi -> ditandai dibaca + menuju tiket. Linimasa tiket memuat Ping.
        $this->actingAs($this->akademik)->patch("/notifications/{$notification->id}/read")->assertRedirect("/staff/tickets/{$ticket->id}");
        $this->assertTrue($notification->fresh()->is_read);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->actingAs($this->akademik)->get("/staff/tickets/{$ticket->id}")
            ->assertSee('Ping dari Admin')->assertSee('Admin FITK → Sari Akademik')->assertSee('Segera selesaikan ya');
        $this->actingAs($this->akademik)->get('/staff/dashboard')->assertDontSee('Teguran dari Admin');

        // Pelapor tidak melihat Ping di linimasa (PRD 10.5).
        $this->actingAs($this->mahasiswa)->get("/tickets/{$ticket->id}")->assertOk()->assertDontSee('Ping dari Admin')->assertDontSee('Segera selesaikan ya');
    }

    public function test_opening_ticket_marks_its_pings_read_and_read_all_works(): void
    {
        $a = $this->ticket(['status' => TicketStatus::Diproses]);
        $b = $this->ticket(['status' => TicketStatus::Diproses]);
        foreach ([$a, $b] as $ticket) {
            $this->actingAs($this->admin)->post("/admin/tickets/{$ticket->id}/ping");
        }

        $this->actingAs($this->akademik)->get("/staff/tickets/{$a->id}")->assertOk();
        $this->assertSame(1, $this->akademik->inAppNotifications()->where('is_read', false)->count());

        $this->actingAs($this->akademik)->patch('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $this->akademik->inAppNotifications()->where('is_read', false)->count());
    }

    public function test_user_cannot_read_someone_elses_notification(): void
    {
        $ticket = $this->ticket(['status' => TicketStatus::Diproses]);
        $this->actingAs($this->admin)->post("/admin/tickets/{$ticket->id}/ping");
        $notification = Notification::first();

        $this->actingAs($this->akademik2)->patch("/notifications/{$notification->id}/read")->assertNotFound();
        $this->assertFalse($notification->fresh()->is_read);
    }

    public function test_admin_sees_ping_history_on_ticket_detail_and_json(): void
    {
        $ticket = $this->ticket(['created' => now()->subHours(30)]);
        $this->actingAs($this->admin)->post("/admin/tickets/{$ticket->id}/ping", ['message' => 'Pesan pertama']);

        // Satu aksi ke 2 staff tampil sebagai SATU baris dengan dua penerima.
        $this->actingAs($this->admin)->get("/admin/tickets/{$ticket->id}")
            ->assertOk()
            ->assertSee('Admin FITK → Rudi Akademik, Sari Akademik')
            ->assertSee('Pesan pertama')
            ->assertSee('Kirim Ping');

        $this->actingAs($this->admin)->getJson("/admin/tickets/{$ticket->id}/pings")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.admin.name', 'Admin FITK')
            ->assertJsonPath('data.0.message', 'Pesan pertama');
    }

    public function test_ping_is_rate_limited_per_admin(): void
    {
        $tickets = collect(range(1, 21))->map(fn () => $this->ticket(['status' => TicketStatus::Diproses]));

        foreach ($tickets->take(20) as $ticket) {
            $this->actingAs($this->admin)->postJson("/admin/tickets/{$ticket->id}/ping")->assertOk();
        }
        $this->actingAs($this->admin)->postJson("/admin/tickets/{$tickets->last()->id}/ping")->assertStatus(429);
        $this->assertSame(20, PingLog::count());
    }

    public function test_admin_of_another_faculty_cannot_ping(): void
    {
        // Admin FEBI yang kebetulan tahu id tiket FITK tetap ditolak.
        $ticket = $this->ticket();
        $this->actingAs($this->adminFebi)->post("/admin/tickets/{$ticket->id}/ping")->assertForbidden();
        $this->assertDatabaseCount('ping_logs', 0);
    }
}
