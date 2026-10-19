<?php

namespace Tests\Feature\AdminMonitoring;

use App\Enums\TicketStatus;
use App\Models\User;

/**
 * Otorisasi halaman Admin M4: hanya Admin Fakultas, dan hanya data fakultasnya sendiri (PRD 2.3, 2.5).
 */
class AdminAccessTest extends AdminTestCase
{
    private function adminUrls(int $ticketId): array
    {
        return ['/admin/queue', '/admin/reports', '/admin/reports/export?format=csv', '/admin/reports/export?format=xlsx', "/admin/tickets/{$ticketId}", "/admin/tickets/{$ticketId}/pings"];
    }

    public function test_staff_end_user_and_super_admin_cannot_open_admin_pages(): void
    {
        $ticket = $this->ticket();

        foreach ([$this->akademik, $this->mahasiswa, User::factory()->superAdmin()->create()] as $user) {
            foreach ($this->adminUrls($ticket->id) as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
            $this->actingAs($user)->post("/admin/tickets/{$ticket->id}/ping", ['message' => 'x'])->assertForbidden();
        }

        $this->assertDatabaseCount('ping_logs', 0);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/queue')->assertRedirect('/login');
        $this->get('/admin/reports')->assertRedirect('/login');
    }

    public function test_admin_fitk_cannot_see_or_ping_febi_ticket(): void
    {
        $febiTicket = $this->ticketFor($this->mahasiswa, $this->cat('KRS belum di-ACC', $this->febi), ['title' => 'Tiket rahasia FEBI']);

        $this->actingAs($this->admin)->get("/admin/tickets/{$febiTicket->id}")->assertForbidden();
        $this->actingAs($this->admin)->getJson("/admin/tickets/{$febiTicket->id}/pings")->assertForbidden();
        $this->actingAs($this->admin)->postJson("/admin/tickets/{$febiTicket->id}/ping")->assertForbidden();
        $this->assertDatabaseCount('ping_logs', 0);

        // Tidak muncul di Monitor Antrean, laporan, maupun file ekspor FITK.
        $this->actingAs($this->admin)->get('/admin/queue?status=semua')->assertOk()->assertDontSee('Tiket rahasia FEBI');
        $this->actingAs($this->admin)->get('/admin/reports')->assertOk()->assertDontSee($febiTicket->ticket_number);
        $csv = $this->actingAs($this->admin)->get('/admin/reports/export?format=csv')->streamedContent();
        $this->assertStringNotContainsString($febiTicket->ticket_number, $csv);

        // Admin FEBI justru melihatnya.
        $this->actingAs($this->adminFebi)->get("/admin/tickets/{$febiTicket->id}")->assertOk()->assertSee('Tiket rahasia FEBI');
        $this->actingAs($this->adminFebi)->get('/admin/queue')->assertSee('Tiket rahasia FEBI');
    }

    public function test_filter_ids_from_another_faculty_are_ignored(): void
    {
        $this->ticket(['title' => 'Tiket FITK biasa']);

        // Kategori & staff milik FEBI tidak boleh dipakai menyaring (dan tidak membocorkan apa pun).
        $this->actingAs($this->admin)->get('/admin/queue?category='.$this->cat('KRS belum di-ACC', $this->febi)->id.'&staff='.$this->staffFebi->id)
            ->assertOk()->assertSee('Tiket FITK biasa');
        $this->actingAs($this->admin)->get('/admin/reports?staff='.$this->staffFebi->id)
            ->assertOk()->assertViewHas('filters', fn ($filters) => $filters->staff === null);
    }

    public function test_admin_without_faculty_gets_403(): void
    {
        $orphan = User::factory()->admin()->create(['faculty_id' => null]);

        $this->actingAs($orphan)->get('/admin/queue')->assertForbidden();
        $this->actingAs($orphan)->get('/admin/reports')->assertForbidden();
        $this->actingAs($orphan)->get('/admin/dashboard')->assertOk()->assertSee('belum ditugaskan ke fakultas');
    }

    public function test_admin_cannot_claim_reply_or_close_tickets(): void
    {
        $waiting = $this->ticket();
        $inProgress = $this->ticket(['status' => TicketStatus::Diproses]);

        // Route staff dijaga middleware role:staff -> 403 untuk Admin.
        $this->actingAs($this->admin)->patch("/staff/tickets/{$waiting->id}/assign")->assertForbidden();
        $this->actingAs($this->admin)->patch("/staff/tickets/{$inProgress->id}/status", ['status' => 'ditolak', 'reason' => 'lainnya', 'reason_note' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->post("/staff/tickets/{$inProgress->id}/comments", ['body' => 'halo'])->assertForbidden();

        $this->assertSame(TicketStatus::Menunggu, $waiting->fresh()->status);
        $this->assertSame(TicketStatus::Diproses, $inProgress->fresh()->status);

        // Detail admin tidak punya form ambil/balas/tutup.
        $this->actingAs($this->admin)->get("/admin/tickets/{$inProgress->id}")
            ->assertOk()
            ->assertDontSee('Ambil Tiket')
            ->assertDontSee('Tandai Selesai')
            ->assertDontSee('name="body"', false);
    }
}
