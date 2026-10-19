<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Tiket Saya (US-04), detail + linimasa, Riwayat (US-05), dan otorisasi antar-pengguna.
 */
class MyTicketsTest extends TicketTestCase
{
    public function test_my_tickets_lists_only_own_waiting_and_in_progress_tickets(): void
    {
        $staff = User::factory()->staff($this->fitk)->create(['name' => 'Sari Staff']);
        $this->ticketFor($this->mahasiswa, null, ['title' => 'Tiket menunggu saya']);
        $this->ticketFor($this->mahasiswa, $this->cat('Keuangan & UKT'), ['title' => 'Tiket diproses saya', 'status' => TicketStatus::Diproses, 'handler_id' => $staff->id, 'assigned_at' => now()]);
        $this->ticketFor($this->mahasiswa, null, ['title' => 'Tiket selesai saya', 'status' => TicketStatus::Selesai, 'resolved_at' => now()]);
        $this->ticketFor(User::factory()->mahasiswa()->create(), null, ['title' => 'Tiket orang lain']);

        $this->actingAs($this->mahasiswa)->get('/tickets')
            ->assertOk()
            ->assertSee('Tiket menunggu saya')
            ->assertSee('Tiket diproses saya')
            ->assertDontSee('Tiket selesai saya')
            ->assertDontSee('Tiket orang lain')
            ->assertSee('Sistem Akademik &amp; Perkuliahan › KRS belum di-ACC', false) // format "Kelompok › Jenis"
            ->assertSee('Keuangan &amp; UKT', false)
            ->assertSee('Menunggu diambil Staff')
            ->assertSee('Ditangani Sari Staff')
            ->assertSee('SLA')
            ->assertSee('24 jam');
    }

    public function test_search_filters_by_ticket_number(): void
    {
        $a = $this->ticketFor($this->mahasiswa, null, ['title' => 'Proyektor rusak']);
        $this->ticketFor($this->mahasiswa, null, ['title' => 'Wifi mati']);

        $this->actingAs($this->mahasiswa)->get('/tickets?q='.$a->ticket_number)
            ->assertSee('Proyektor rusak')->assertDontSee('Wifi mati');
    }

    public function test_empty_state_when_no_active_ticket(): void
    {
        $this->actingAs($this->mahasiswa)->get('/tickets')->assertOk()->assertSee('Belum ada tiket aktif');
    }

    public function test_detail_shows_timeline_description_and_attachments(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 4, 14, 20));
        $staff = User::factory()->staff($this->fitk)->create(['name' => 'Staff A']);
        $ticket = $this->ticketFor($this->mahasiswa, null, [
            'title' => 'Portal akademik error', 'description' => 'Sejak 1 September tidak bisa login.',
            'status' => TicketStatus::Diproses, 'handler_id' => $staff->id,
            'assigned_at' => now()->addMinutes(25), 'first_responded_at' => now()->addMinutes(40),
        ]);
        $ticket->attachments()->create(['file_name' => 'screenshot_error.png', 'file_path' => 'attachments/x.png', 'mime_type' => 'image/png', 'file_size' => 2048]);

        $this->actingAs($this->mahasiswa)->get("/tickets/{$ticket->id}")
            ->assertOk()
            ->assertSee($ticket->ticket_number)
            ->assertSee('Portal akademik error')
            ->assertSee('Sejak 1 September tidak bisa login.')
            ->assertSee('screenshot_error.png')
            ->assertSee(route('attachments.show', $ticket->attachments->first()))
            ->assertSeeInOrder(['Riwayat Aktivitas', 'Tiket dibuat', '4 Okt 2026, 14:20', 'Diambil oleh Staff A', '4 Okt 2026, 14:45', 'Respons pertama Staff', '4 Okt 2026, 15:00', 'Selesai / Ditolak', 'Belum']);
    }

    public function test_waiting_ticket_detail_has_no_countdown(): void
    {
        $ticket = $this->ticketFor($this->mahasiswa);

        $this->actingAs($this->mahasiswa)->get("/tickets/{$ticket->id}")
            ->assertSee('Menunggu diambil Staff')
            ->assertSee('Tiket tidak punya batas kedaluwarsa')
            ->assertDontSee('sisa');
    }

    public function test_end_user_cannot_see_someone_elses_ticket(): void
    {
        $ticket = $this->ticketFor(User::factory()->mahasiswa()->create());

        $this->actingAs($this->mahasiswa)->get("/tickets/{$ticket->id}")->assertForbidden();
        $this->actingAs($this->mahasiswa)->get("/history/{$ticket->id}")->assertForbidden();
    }

    public function test_staff_and_admin_cannot_use_end_user_ticket_pages(): void
    {
        $ticket = $this->ticketFor($this->mahasiswa);

        foreach ([User::factory()->staff($this->fitk)->create(), User::factory()->admin($this->fitk)->create(), User::factory()->superAdmin()->create()] as $user) {
            $this->actingAs($user)->get('/tickets')->assertForbidden();
            $this->actingAs($user)->get("/tickets/{$ticket->id}")->assertForbidden();
            $this->actingAs($user)->get('/history')->assertForbidden();
        }
    }

    public function test_unknown_ticket_returns_404(): void
    {
        $this->actingAs($this->mahasiswa)->get('/tickets/999999')->assertNotFound();
    }

    public function test_history_lists_only_own_closed_tickets(): void
    {
        $this->ticketFor($this->mahasiswa, null, ['title' => 'Masih menunggu']);
        $this->ticketFor($this->mahasiswa, null, ['title' => 'Sudah selesai', 'status' => TicketStatus::Selesai, 'resolved_at' => now()]);
        $this->ticketFor($this->mahasiswa, null, ['title' => 'Ditolak saja', 'status' => TicketStatus::Ditolak, 'resolved_at' => now(), 'rejection_reason' => 'Data tidak lengkap']);
        $this->ticketFor(User::factory()->mahasiswa()->create(), null, ['title' => 'Selesai orang lain', 'status' => TicketStatus::Selesai, 'resolved_at' => now()]);

        $this->actingAs($this->mahasiswa)->get('/history')
            ->assertOk()
            ->assertSee('Sudah selesai')
            ->assertSee('Ditolak saja')
            ->assertSee('Alasan: Data tidak lengkap')
            ->assertDontSee('Masih menunggu')
            ->assertDontSee('Selesai orang lain');
    }

    public function test_closed_ticket_detail_lives_under_history_and_shows_outcome(): void
    {
        $rejected = $this->ticketFor($this->mahasiswa, null, [
            'status' => TicketStatus::Ditolak, 'resolved_at' => now(), 'rejection_reason' => Ticket::REJECTION_WRONG_TARGET.': seharusnya ke FEBI',
        ]);

        $this->actingAs($this->mahasiswa)->get("/tickets/{$rejected->id}")->assertRedirect("/history/{$rejected->id}");

        $this->actingAs($this->mahasiswa)->get("/history/{$rejected->id}")
            ->assertOk()
            ->assertSee('Alasan penolakan')
            ->assertSee('seharusnya ke FEBI')
            ->assertSee('Buat Ulang Tiket')
            ->assertSee(route('tickets.create', ['from' => $rejected->id]), false)
            ->assertSee('Tiket sudah ditutup, thread tidak dapat dibalas lagi.');
    }

    public function test_recreate_button_only_for_wrong_target_rejection(): void
    {
        $rejected = $this->ticketFor($this->mahasiswa, null, ['status' => TicketStatus::Ditolak, 'resolved_at' => now(), 'rejection_reason' => 'NIM tidak ditemukan']);

        $this->actingAs($this->mahasiswa)->get("/history/{$rejected->id}")->assertOk()->assertDontSee('Buat Ulang Tiket');
    }

    public function test_open_ticket_under_history_url_redirects_to_tickets(): void
    {
        $ticket = $this->ticketFor($this->mahasiswa);

        $this->actingAs($this->mahasiswa)->get("/history/{$ticket->id}")->assertRedirect("/tickets/{$ticket->id}");
    }

    public function test_resolved_ticket_shows_proofs_and_rating(): void
    {
        $staff = User::factory()->staff($this->fitk)->create();
        $ticket = $this->ticketFor($this->mahasiswa, null, ['status' => TicketStatus::Selesai, 'resolved_at' => now(), 'handler_id' => $staff->id]);
        $proof = $ticket->resolutionProofs()->create(['file_name' => 'krs-acc.pdf', 'file_path' => 'proofs/a.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1000, 'uploaded_by' => $staff->id]);
        $ticket->rating()->create(['user_id' => $this->mahasiswa->id, 'score' => 4, 'comment' => 'Cukup cepat']);

        $this->actingAs($this->mahasiswa)->get("/history/{$ticket->id}")
            ->assertSee('Bukti penyelesaian')
            ->assertSee('krs-acc.pdf')
            ->assertSee(route('proofs.show', $proof))
            ->assertSee('4 dari 5 bintang')
            ->assertSee('Cukup cepat');
    }
}
