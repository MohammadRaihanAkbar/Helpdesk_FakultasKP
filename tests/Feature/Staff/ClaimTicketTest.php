<?php

namespace Tests\Feature\Staff;

use App\Enums\TicketStatus;
use App\Exceptions\BusinessRuleException;
use App\Services\Tickets\TicketWorkflowService;

/**
 * Ambil Tiket (US-07, F-TKS-02, PRD 9.3 PATCH assign): Menunggu -> Diproses, handler_id + assigned_at,
 * aman dari balapan (409 TICKET_ALREADY_ASSIGNED), hanya staff yang berhak atas kelompoknya.
 */
class ClaimTicketTest extends StaffTestCase
{
    public function test_staff_claims_ticket_from_queue(): void
    {
        $ticket = $this->waiting();

        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/assign")
            ->assertRedirect("/staff/tickets/{$ticket->id}")
            ->assertSessionHas('status', "Tiket {$ticket->ticket_number} berhasil diambil dan berstatus Diproses.");

        $ticket->refresh();
        $this->assertSame(TicketStatus::Diproses, $ticket->status);
        $this->assertSame($this->akademik->id, $ticket->handler_id);
        $this->assertNotNull($ticket->assigned_at);
        $this->assertNull($ticket->first_responded_at);

        // Pindah dari Antrean ke Tiket Saya.
        $this->actingAs($this->akademik)->get('/staff/queue')->assertDontSee($ticket->title);
        $this->actingAs($this->akademik)->get('/staff/tickets')->assertSee($ticket->title);
    }

    public function test_claim_json_follows_prd_shape(): void
    {
        $ticket = $this->waiting();

        $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$ticket->id}/assign")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'diproses')
            ->assertJsonPath('data.old_status', 'menunggu')
            ->assertJsonPath('data.handler.id', $this->akademik->id)
            ->assertJsonPath('message', "Tiket {$ticket->ticket_number} berhasil diambil dan berstatus Diproses.");
    }

    public function test_second_staff_gets_409_when_ticket_already_taken(): void
    {
        $ticket = $this->waiting();

        $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$ticket->id}/assign")->assertOk();

        $this->actingAs($this->akademik2)->patchJson("/staff/tickets/{$ticket->id}/assign")
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'TICKET_ALREADY_ASSIGNED');

        $this->assertSame($this->akademik->id, $ticket->fresh()->handler_id);
    }

    public function test_second_staff_gets_friendly_flash_on_web(): void
    {
        $ticket = $this->waiting();
        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/assign");

        $this->actingAs($this->akademik2)->from('/staff/queue')->patch("/staff/tickets/{$ticket->id}/assign")
            ->assertRedirect('/staff/queue')
            ->assertSessionHasErrors([BusinessRuleException::GENERAL_FIELD => 'Tiket ini sudah diambil oleh staff lain. Silakan pilih tiket lain di antrean.']);

        $this->actingAs($this->akademik2)->followingRedirects()->from('/staff/queue')->patch("/staff/tickets/{$ticket->id}/assign")
            ->assertOk()->assertSee('sudah diambil oleh staff lain');
    }

    /**
     * Simulasi balapan: dua staff MEMBACA tiket yang sama saat masih Menunggu (dua objek model "basi"),
     * lalu sama-sama menekan Ambil. Update bersyarat membuat hanya satu yang berhasil.
     */
    public function test_race_condition_only_one_staff_wins(): void
    {
        $ticket = $this->waiting();
        $seenByA = $ticket->fresh();
        $seenByB = $ticket->fresh();
        $workflow = app(TicketWorkflowService::class);

        $workflow->assign($seenByA, $this->akademik);

        try {
            $workflow->assign($seenByB, $this->akademik2);
            $this->fail('Staff kedua seharusnya kalah.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('TICKET_ALREADY_ASSIGNED', $e->errorCode);
            $this->assertSame(409, $e->status);
        }

        $this->assertSame($this->akademik->id, $ticket->fresh()->handler_id);
    }

    public function test_double_click_by_same_staff_is_harmless(): void
    {
        $ticket = $this->waiting();

        $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$ticket->id}/assign")->assertOk();
        $assignedAt = $ticket->fresh()->assigned_at;

        $this->travel(5)->minutes();
        $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$ticket->id}/assign")->assertOk();
        $this->assertEquals($assignedAt, $ticket->fresh()->assigned_at);
    }

    public function test_only_staff_with_matching_specialty_and_faculty_can_claim(): void
    {
        $ticket = $this->waiting();

        $this->actingAs($this->umum)->patch("/staff/tickets/{$ticket->id}/assign")->assertForbidden();               // kelompok lain
        $this->actingAs($this->tanpaSpesialisasi)->patch("/staff/tickets/{$ticket->id}/assign")->assertForbidden();  // tanpa spesialisasi
        $this->actingAs($this->staffFebi)->patch("/staff/tickets/{$ticket->id}/assign")->assertForbidden();          // fakultas lain
        $this->actingAs($this->admin)->patch("/staff/tickets/{$ticket->id}/assign")->assertForbidden();              // admin tidak menangani tiket
        $this->actingAs($this->mahasiswa)->patch("/staff/tickets/{$ticket->id}/assign")->assertForbidden();          // pelapor

        $this->actingAs($this->nonaktif)->patch("/staff/tickets/{$ticket->id}/assign")->assertRedirect('/login');   // nonaktif: dikeluarkan
        $this->assertFalse($this->nonaktif->can('claim', $ticket));

        $this->assertSame(TicketStatus::Menunggu, $ticket->fresh()->status);
    }

    public function test_closed_ticket_cannot_be_claimed(): void
    {
        $ticket = $this->waiting(null, ['status' => TicketStatus::Ditolak, 'resolved_at' => now(), 'rejection_reason' => 'x']);

        $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$ticket->id}/assign")
            ->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
    }

    public function test_sensitive_identity_appears_only_after_claim(): void
    {
        $ticket = $this->waiting($this->cat('Integritas Dosen'), ['title' => 'Dosen tidak hadir']);

        $this->actingAs($this->dekanat)->get("/staff/tickets/{$ticket->id}")
            ->assertOk()->assertSee('Identitas dirahasiakan')->assertDontSee('Ahmad Mahasiswa')->assertDontSee($this->mahasiswa->nim);

        $this->actingAs($this->dekanat)->patch("/staff/tickets/{$ticket->id}/assign");

        $this->actingAs($this->dekanat)->get("/staff/tickets/{$ticket->id}")
            ->assertOk()->assertSee('Ahmad Mahasiswa')->assertSee($this->mahasiswa->nim)->assertDontSee('Identitas dirahasiakan');
    }

    public function test_detail_of_waiting_ticket_shows_claim_button_for_specialist(): void
    {
        $ticket = $this->waiting();

        $this->actingAs($this->akademik)->get("/staff/tickets/{$ticket->id}")
            ->assertOk()
            ->assertSee('Ambil Tiket')
            ->assertSee('Belum diambil')
            ->assertDontSee('Tutup tiket');

        $this->actingAs($this->umum)->get("/staff/tickets/{$ticket->id}")->assertForbidden();
        $this->actingAs($this->staffFebi)->get("/staff/tickets/{$ticket->id}")->assertForbidden();
    }
}
