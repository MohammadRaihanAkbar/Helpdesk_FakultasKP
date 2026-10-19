<?php

namespace Tests\Feature\Tickets;

use App\Models\Ticket;
use App\Services\Tickets\TicketNumberGenerator;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Nomor tiket TKT-YYYYMM-XXXX (F-TKU-02): berurutan per bulan, unik, tahan bentrok.
 */
class TicketNumberTest extends TicketTestCase
{
    private function next(): string
    {
        return DB::transaction(fn () => app(TicketNumberGenerator::class)->next());
    }

    public function test_numbers_are_sequential_within_a_month_and_reset_next_month(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 31, 23));
        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload());
        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload());

        $this->travelTo(Carbon::create(2026, 11, 1, 8));
        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload());

        $this->assertSame(['TKT-202610-0001', 'TKT-202610-0002', 'TKT-202611-0001'], Ticket::orderBy('id')->pluck('ticket_number')->all());
    }

    public function test_sequence_continues_past_9999_without_breaking_order(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 1));
        $this->ticketFor($this->mahasiswa, null, ['ticket_number' => 'TKT-202610-9999']);
        $this->assertSame('TKT-202610-10000', $this->next());

        $this->ticketFor($this->mahasiswa, null, ['ticket_number' => 'TKT-202610-10000']);
        $this->assertSame('TKT-202610-10001', $this->next());
    }

    public function test_other_months_do_not_affect_the_sequence(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 5));
        $this->ticketFor($this->mahasiswa, null, ['ticket_number' => 'TKT-202609-0057']);

        $this->assertSame('TKT-202610-0001', $this->next());
    }

    public function test_service_retries_when_number_collides(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 5));
        $this->ticketFor($this->mahasiswa, null, ['ticket_number' => 'TKT-202610-0001']);

        // Simulasikan race condition: generator "terlambat" dan mengembalikan nomor yang sudah dipakai
        // pada percobaan pertama. TicketService harus mengulang dan berhasil dengan nomor berikutnya.
        $this->app->instance(TicketNumberGenerator::class, new class extends TicketNumberGenerator
        {
            public int $calls = 0;

            public function next(?CarbonInterface $at = null): string
            {
                return ++$this->calls === 1 ? 'TKT-202610-0001' : parent::next($at);
            }
        });

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(['TKT-202610-0001', 'TKT-202610-0002'], Ticket::orderBy('id')->pluck('ticket_number')->all());
    }
}
