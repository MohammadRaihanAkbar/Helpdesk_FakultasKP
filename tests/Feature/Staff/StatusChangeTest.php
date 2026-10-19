<?php

namespace Tests\Feature\Staff;

use App\Enums\TicketStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ResolutionProof;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketWorkflowService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Ubah status (US-08, F-TKS-05, F-TKS-07, PRD 4.5 & 9.3 PATCH status):
 *   Diproses -> Selesai  wajib bukti (400 "Bukti penyelesaian wajib dilampirkan.")
 *   Diproses -> Ditolak  wajib alasan (400), alasan baku "Salah fakultas/kategori"
 *   keduanya mengisi resolved_at; transisi lain -> 409 INVALID_STATUS_TRANSITION.
 */
class StatusChangeTest extends StaffTestCase
{
    public function test_resolve_with_proofs(): void
    {
        $ticket = $this->inProgress();

        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/status", [
            'status' => 'selesai',
            'proofs' => [$this->png('krs-sudah-acc.png'), $this->pdf('surat.pdf')],
        ])->assertRedirect("/staff/history/{$ticket->id}")->assertSessionHas('status');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Selesai, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertSame(2, $ticket->resolutionProofs()->count());

        $proof = $ticket->resolutionProofs()->first();
        $this->assertSame($this->akademik->id, $proof->uploaded_by);
        $this->assertSame('krs-sudah-acc.png', $proof->file_name);
        $this->assertStringStartsWith('proofs/', $proof->file_path);
        Storage::disk('local')->assertExists($proof->file_path); // disk privat
    }

    public function test_resolve_json_reports_sla_met(): void
    {
        $onTime = $this->inProgress(null, null, ['created_at' => now()->subHours(10)]);   // KRS: SLA 24 jam
        $late = $this->inProgress(null, null, ['created_at' => now()->subHours(30)]);

        $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$onTime->id}/status", ['status' => 'selesai', 'proofs' => [$this->png()]])
            ->assertOk()
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.old_status', 'diproses')
            ->assertJsonPath('data.sla_target_hours', 24)
            ->assertJsonPath('data.sla_met', true)
            ->assertJsonPath('data.resolution_proofs.0.file_name', 'bukti.png')
            ->assertJsonPath('message', 'Status tiket berhasil diubah menjadi Selesai.');

        $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$late->id}/status", ['status' => 'selesai', 'proofs' => [$this->png()]])
            ->assertOk()->assertJsonPath('data.sla_met', false);
    }

    public function test_resolve_without_proof_returns_400(): void
    {
        $ticket = $this->inProgress();

        $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$ticket->id}/status", ['status' => 'selesai'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'PROOF_REQUIRED')
            ->assertJsonPath('error.message', 'Bukti penyelesaian wajib dilampirkan.');

        $this->actingAs($this->akademik)->from("/staff/tickets/{$ticket->id}")->patch("/staff/tickets/{$ticket->id}/status", ['status' => 'selesai'])
            ->assertRedirect("/staff/tickets/{$ticket->id}")
            ->assertSessionHasErrors(['proofs' => 'Bukti penyelesaian wajib dilampirkan.']);

        $this->assertSame(TicketStatus::Diproses, $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->resolved_at);
    }

    public function test_proof_file_rules(): void
    {
        $ticket = $this->inProgress();
        $send = fn (array $proofs) => $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$ticket->id}/status", ['status' => 'selesai', 'proofs' => $proofs]);

        $send([$this->realFile('foto.png', 'ini bukan gambar')])->assertStatus(422)->assertJsonValidationErrors('proofs.0');
        $send([UploadedFile::fake()->create('laporan.docx', 10)])->assertStatus(422);
        $send([UploadedFile::fake()->image('besar.jpg')->size(6000)])->assertStatus(422)->assertJsonValidationErrors('proofs.0');
        $send([$this->png('1.png'), $this->png('2.png'), $this->png('3.png'), $this->png('4.png')])->assertStatus(422)->assertJsonValidationErrors('proofs');

        $this->assertSame(0, ResolutionProof::count());
        $this->assertSame(TicketStatus::Diproses, $ticket->fresh()->status);
    }

    public function test_reject_requires_reason(): void
    {
        $ticket = $this->inProgress();

        $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$ticket->id}/status", ['status' => 'ditolak'])
            ->assertStatus(400)->assertJsonPath('error.code', 'REJECTION_REASON_REQUIRED');

        $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$ticket->id}/status", ['status' => 'ditolak', 'reason' => 'lainnya'])
            ->assertStatus(422)->assertJsonValidationErrors('reason_note');

        $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$ticket->id}/status", ['status' => 'ditolak', 'reason' => 'ngawur'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->assertSame(TicketStatus::Diproses, $ticket->fresh()->status);
    }

    public function test_reject_with_standard_reasons(): void
    {
        $a = $this->inProgress();
        $b = $this->inProgress();
        $c = $this->inProgress();

        $this->actingAs($this->akademik)->patch("/staff/tickets/{$a->id}/status", ['status' => 'ditolak', 'reason' => 'duplikat'])
            ->assertRedirect("/staff/history/{$a->id}");
        $this->actingAs($this->akademik)->patch("/staff/tickets/{$b->id}/status", ['status' => 'ditolak', 'reason' => 'lainnya', 'reason_note' => 'Sudah diurus lewat BAAK pusat.']);
        $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$c->id}/status", ['status' => 'ditolak', 'rejection_reason' => 'Teks alasan langsung dari Postman'])
            ->assertOk()->assertJsonPath('data.sla_met', null)->assertJsonPath('data.status', 'ditolak');

        $this->assertSame('Tiket ganda (sudah dilaporkan di tiket lain)', $a->fresh()->rejection_reason);
        $this->assertSame('Sudah diurus lewat BAAK pusat.', $b->fresh()->rejection_reason);
        $this->assertSame('Teks alasan langsung dari Postman', $c->fresh()->rejection_reason);
        foreach ([$a, $b, $c] as $ticket) {
            $this->assertSame(TicketStatus::Ditolak, $ticket->fresh()->status);
            $this->assertNotNull($ticket->fresh()->resolved_at);
            $this->assertFalse($ticket->fresh()->isRejectedForWrongTarget());
        }
    }

    /** Alur penuh keputusan M2 no. 7: staff menolak "Salah fakultas/kategori" -> pelapor Buat Ulang Tiket. */
    public function test_wrong_target_rejection_enables_recreate_end_to_end(): void
    {
        $ticket = $this->inProgress(null, $this->cat('Jadwal mata kuliah'), ['title' => 'Jadwal Akuntansi bentrok']);

        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/status", [
            'status' => 'ditolak', 'reason' => 'salah_tujuan', 'reason_note' => 'mata kuliah ini milik FEBI',
        ]);

        $ticket->refresh();
        $this->assertSame('Salah fakultas/kategori: mata kuliah ini milik FEBI', $ticket->rejection_reason);
        $this->assertTrue($ticket->isRejectedForWrongTarget());

        $this->actingAs($this->mahasiswa)->get("/history/{$ticket->id}")
            ->assertSee('Salah fakultas/kategori: mata kuliah ini milik FEBI')
            ->assertSee('Buat Ulang Tiket')
            ->assertSee(route('tickets.create', ['from' => $ticket->id]), false);

        $this->actingAs($this->mahasiswa)->get("/tickets/new?from={$ticket->id}")->assertOk()->assertSee('Jadwal Akuntansi bentrok');

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload([
            'faculty_id' => $this->febi->id,
            'category_id' => $this->cat('Jadwal mata kuliah', $this->febi)->id,
            'title' => 'Jadwal Akuntansi bentrok',
            'from' => $ticket->id,
        ]))->assertRedirect();

        $new = Ticket::latest('id')->first();
        $this->assertNotSame($ticket->id, $new->id);
        $this->assertSame($this->febi->id, $new->faculty_id);
        $this->assertSame(TicketStatus::Menunggu, $new->status);
        $this->assertSame(TicketStatus::Ditolak, $ticket->fresh()->status); // tiket lama tetap Ditolak

        // Tiket baru langsung masuk antrean staff FEBI.
        $this->actingAs($this->staffFebi)->get('/staff/queue')->assertSee($new->ticket_number);
    }

    public function test_preset_without_note_is_stored_as_is(): void
    {
        $ticket = $this->inProgress();

        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/status", ['status' => 'ditolak', 'reason' => 'salah_tujuan']);

        $this->assertSame(Ticket::REJECTION_WRONG_TARGET, $ticket->fresh()->rejection_reason);
    }

    public function test_illegal_transitions_are_rejected(): void
    {
        $inProgress = $this->inProgress();
        $done = $this->inProgress(null, null, ['status' => TicketStatus::Selesai, 'resolved_at' => now()]);
        $rejected = $this->inProgress(null, null, ['status' => TicketStatus::Ditolak, 'resolved_at' => now(), 'rejection_reason' => 'x']);

        $patch = fn (Ticket $ticket, array $data) => $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$ticket->id}/status", $data);

        // Tidak bisa mundur / tetap di tempat lewat PATCH status.
        $patch($inProgress, ['status' => 'menunggu'])->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
        $patch($inProgress, ['status' => 'diproses'])->assertStatus(409);
        $patch($inProgress, ['status' => 'kedaluwarsa'])->assertStatus(422); // status yang tidak ada (dihapus PRD v6.0)

        // Tiket yang sudah ditutup tidak bisa dibuka atau ditutup ulang.
        $patch($done, ['status' => 'ditolak', 'reason' => 'duplikat'])->assertStatus(409);
        $patch($done, ['status' => 'selesai', 'proofs' => [$this->png()]])->assertStatus(409);
        $patch($rejected, ['status' => 'selesai', 'proofs' => [$this->png()]])->assertStatus(409);

        $this->assertSame(TicketStatus::Diproses, $inProgress->fresh()->status);
        $this->assertSame(TicketStatus::Selesai, $done->fresh()->status);
        $this->assertSame(TicketStatus::Ditolak, $rejected->fresh()->status);
        $this->assertSame(0, ResolutionProof::count()); // tidak ada berkas yatim
    }

    public function test_waiting_ticket_cannot_jump_to_closed(): void
    {
        $ticket = $this->waiting();

        // Lewat HTTP: belum ada penangan, jadi tidak ada yang berhak (403).
        $this->actingAs($this->akademik)->patchJson("/staff/tickets/{$ticket->id}/status", ['status' => 'selesai', 'proofs' => [$this->png()]])->assertForbidden();

        // Lewat service (mis. dipanggil kode lain): Menunggu -> Selesai/Ditolak tetap ditolak 409.
        foreach ([TicketStatus::Selesai, TicketStatus::Ditolak] as $target) {
            try {
                app(TicketWorkflowService::class)->changeStatus($ticket, $this->akademik, $target, [$this->png()], 'alasan');
                $this->fail('Menunggu tidak boleh langsung ditutup.');
            } catch (BusinessRuleException $e) {
                $this->assertSame('INVALID_STATUS_TRANSITION', $e->errorCode);
                $this->assertStringContainsString('Ambil tiket terlebih dahulu', $e->getMessage());
            }
        }

        $this->assertSame(TicketStatus::Menunggu, $ticket->fresh()->status);
    }

    public function test_status_machine_matches_prd(): void
    {
        $this->assertTrue(TicketStatus::Menunggu->canTransitionTo(TicketStatus::Diproses));
        $this->assertTrue(TicketStatus::Diproses->canTransitionTo(TicketStatus::Selesai));
        $this->assertTrue(TicketStatus::Diproses->canTransitionTo(TicketStatus::Ditolak));

        $this->assertFalse(TicketStatus::Menunggu->canTransitionTo(TicketStatus::Selesai));
        $this->assertFalse(TicketStatus::Menunggu->canTransitionTo(TicketStatus::Ditolak));
        $this->assertFalse(TicketStatus::Diproses->canTransitionTo(TicketStatus::Menunggu));
        foreach (TicketStatus::cases() as $to) {
            $this->assertFalse(TicketStatus::Selesai->canTransitionTo($to));
            $this->assertFalse(TicketStatus::Ditolak->canTransitionTo($to));
        }
    }

    public function test_only_the_handler_can_change_status(): void
    {
        $ticket = $this->inProgress($this->akademik);
        $data = ['status' => 'ditolak', 'reason' => 'duplikat'];

        $this->actingAs($this->akademik2)->patch("/staff/tickets/{$ticket->id}/status", $data)->assertForbidden(); // sesama spesialis
        $this->actingAs($this->staffFebi)->patch("/staff/tickets/{$ticket->id}/status", $data)->assertForbidden();
        $this->actingAs($this->tanpaSpesialisasi)->patch("/staff/tickets/{$ticket->id}/status", $data)->assertForbidden();
        $this->actingAs($this->admin)->patch("/staff/tickets/{$ticket->id}/status", $data)->assertForbidden();
        $this->actingAs($this->mahasiswa)->patch("/staff/tickets/{$ticket->id}/status", $data)->assertForbidden();

        // Penangan yang kemudian dinonaktifkan Admin langsung dikeluarkan.
        $this->akademik->update(['is_active' => false]);
        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/status", $data)->assertRedirect('/login');

        $this->assertSame(TicketStatus::Diproses, $ticket->fresh()->status);
    }

    public function test_proofs_are_downloadable_only_by_people_who_can_see_the_ticket(): void
    {
        $ticket = $this->inProgress();
        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/status", ['status' => 'selesai', 'proofs' => [$this->pdf('bukti.pdf')]]);
        $proof = $ticket->resolutionProofs()->first();

        $this->actingAs($this->mahasiswa)->get("/proofs/{$proof->id}")->assertOk()->assertDownload('bukti.pdf');
        $this->actingAs($this->akademik)->get("/proofs/{$proof->id}")->assertOk();
        $this->actingAs($this->admin)->get("/proofs/{$proof->id}")->assertOk();
        $this->actingAs(User::factory()->mahasiswa($this->fitk)->create())->get("/proofs/{$proof->id}")->assertForbidden();
        $this->actingAs($this->staffFebi)->get("/proofs/{$proof->id}")->assertForbidden();
    }

    public function test_reporter_sees_outcome_in_history(): void
    {
        $ticket = $this->inProgress();
        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/status", ['status' => 'selesai', 'proofs' => [$this->pdf('bukti-selesai.pdf')]]);

        $this->actingAs($this->mahasiswa)->get("/tickets/{$ticket->id}")->assertRedirect("/history/{$ticket->id}");
        $this->actingAs($this->mahasiswa)->get("/history/{$ticket->id}")
            ->assertSee('bukti-selesai.pdf')
            ->assertSee('Beri penilaian layanan');
    }
}
