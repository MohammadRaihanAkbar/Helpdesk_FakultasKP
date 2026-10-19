<?php

namespace Tests\Unit;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Perhitungan SLA di model Ticket (tanpa menyimpan ke database): slaDeadline, slaState (merah/kuning), slaMet.
 * Memakai Tests\TestCase (aplikasi Laravel menyala) karena cast tanggal model butuh container Laravel.
 */
class SlaTest extends TestCase
{
    private function ticket(TicketStatus $status, string $created, ?string $resolved = null, int $hours = 24): Ticket
    {
        $ticket = new Ticket;
        $ticket->status = $status;
        $ticket->sla_target_hours = $hours;
        $ticket->created_at = Carbon::parse($created);
        $ticket->resolved_at = $resolved ? Carbon::parse($resolved) : null;

        return $ticket;
    }

    public function test_deadline_and_open_states(): void
    {
        $ticket = $this->ticket(TicketStatus::Menunggu, '2026-10-01 08:00');

        $this->assertEquals(Carbon::parse('2026-10-02 08:00'), $ticket->slaDeadline());
        $this->assertSame('ok', $ticket->slaState(Carbon::parse('2026-10-02 01:59')));      // sisa 6j01m (> 25%)
        $this->assertSame('near', $ticket->slaState(Carbon::parse('2026-10-02 02:00')));    // sisa tepat 6 jam = 25%
        $this->assertSame('near', $ticket->slaState(Carbon::parse('2026-10-02 08:00')));    // tepat di batas
        $this->assertSame('overdue', $ticket->slaState(Carbon::parse('2026-10-02 08:01')));
        $this->assertNull($ticket->slaMet());
    }

    public function test_sla_met_only_for_resolved(): void
    {
        $this->assertTrue($this->ticket(TicketStatus::Selesai, '2026-10-01 08:00', '2026-10-02 08:00')->slaMet());
        $this->assertFalse($this->ticket(TicketStatus::Selesai, '2026-10-01 08:00', '2026-10-02 08:01')->slaMet());
        $this->assertNull($this->ticket(TicketStatus::Ditolak, '2026-10-01 08:00', '2026-10-01 09:00')->slaMet());
        $this->assertNull($this->ticket(TicketStatus::Selesai, '2026-10-01 08:00')->slaState());
    }

    public function test_duration_format(): void
    {
        $from = Carbon::parse('2026-10-01 08:00');

        $this->assertSame('kurang dari 1 menit', Format::duration($from, $from->copy()->addSeconds(30)));
        $this->assertSame('12 menit', Format::duration($from, $from->copy()->addMinutes(12)));
        $this->assertSame('5 jam 10 menit', Format::duration($from, $from->copy()->addMinutes(310)));
        $this->assertSame('2 hari 3 jam', Format::duration($from->copy()->addHours(51), $from));
    }
}
