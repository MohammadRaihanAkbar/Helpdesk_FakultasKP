<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Kategori sensitif "Integritas Dosen" (PRD 2.7 aturan 10): identitas pelapor hanya untuk
 * Staff penangan dan Admin Fakultas. Disiapkan di M2 lewat TicketPolicy::viewReporterIdentity,
 * dipakai halaman staff/admin di M3–M4.
 */
class SensitiveReporterTest extends TicketTestCase
{
    public function test_sensitive_flag_comes_from_the_standard_group(): void
    {
        $this->assertTrue($this->cat('Integritas Dosen')->isSensitive());
        $this->assertFalse($this->cat('Keuangan & UKT')->isSensitive());
        $this->assertFalse($this->cat('KRS belum di-ACC')->isSensitive());
    }

    public function test_reporter_identity_on_sensitive_ticket(): void
    {
        $handler = User::factory()->staff($this->fitk)->create();
        $sameSpecialty = User::factory()->staff($this->fitk)->create();
        $sameSpecialty->specialties()->attach($this->cat('Integritas Dosen'));
        $adminFitk = User::factory()->admin($this->fitk)->create();
        $adminFebi = User::factory()->admin($this->febi)->create();
        $otherStudent = User::factory()->mahasiswa($this->fitk)->create();

        $ticket = $this->ticketFor($this->mahasiswa, $this->cat('Integritas Dosen'), [
            'status' => TicketStatus::Diproses, 'handler_id' => $handler->id, 'assigned_at' => now(),
        ]);

        $can = fn (User $user) => Gate::forUser($user)->allows('viewReporterIdentity', $ticket);

        $this->assertTrue($can($this->mahasiswa), 'pelapor sendiri');
        $this->assertTrue($can($handler), 'staff penangan');
        $this->assertTrue($can($adminFitk), 'admin fakultas tujuan');
        $this->assertFalse($can($sameSpecialty), 'staff lain dengan spesialisasi sama (melihat tiket, tapi bukan identitas)');
        $this->assertTrue(Gate::forUser($sameSpecialty)->allows('view', $ticket));
        $this->assertFalse($can($adminFebi), 'admin fakultas lain');
        $this->assertFalse($can($otherStudent), 'pelapor lain');
    }

    public function test_identity_on_normal_ticket_follows_view_permission(): void
    {
        $specialist = User::factory()->staff($this->fitk)->create();
        $specialist->specialties()->attach($this->cat('Sistem Akademik & Perkuliahan'));
        $ticket = $this->ticketFor($this->mahasiswa);

        $this->assertTrue(Gate::forUser($specialist)->allows('viewReporterIdentity', $ticket));
        $this->assertFalse(Gate::forUser(User::factory()->staff($this->fitk)->create())->allows('viewReporterIdentity', $ticket));
    }

    public function test_sensitive_ticket_detail_is_labelled_for_reporter(): void
    {
        $ticket = $this->ticketFor($this->mahasiswa, $this->cat('Integritas Dosen'));

        $this->actingAs($this->mahasiswa)->get("/tickets/{$ticket->id}")->assertOk()->assertSee('Sensitif');
    }
}
