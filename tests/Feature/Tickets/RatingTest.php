<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketStatus;
use App\Models\Rating;
use App\Models\Ticket;
use App\Models\User;

/**
 * Rating CSAT (US-05, F-TKU-07, tabel ratings): bintang 1–5 + komentar opsional (maks. 500),
 * hanya pelapor pemilik tiket Selesai, satu kali per tiket.
 */
class RatingTest extends TicketTestCase
{
    private User $staff;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->staff($this->fitk)->create(['name' => 'Sari Staff']);
        $this->staff->specialties()->attach($this->cat('Sistem Akademik & Perkuliahan'));
        $this->ticket = $this->ticketFor($this->mahasiswa, null, [
            'status' => TicketStatus::Selesai, 'handler_id' => $this->staff->id,
            'assigned_at' => now()->subHours(5), 'resolved_at' => now(),
        ]);
    }

    public function test_form_appears_on_resolved_ticket_without_rating(): void
    {
        $this->actingAs($this->mahasiswa)->get("/history/{$this->ticket->id}")
            ->assertOk()
            ->assertSee('Beri penilaian layanan')
            ->assertSee(route('tickets.rating.store', $this->ticket));

        $this->actingAs($this->mahasiswa)->get('/history')->assertSee('Beri rating');
    }

    public function test_reporter_rates_once(): void
    {
        $this->actingAs($this->mahasiswa)->post("/tickets/{$this->ticket->id}/rating", ['score' => 4, 'comment' => '  Cepat, terima kasih.  '])
            ->assertRedirect("/history/{$this->ticket->id}")
            ->assertSessionHas('status', 'Terima kasih atas feedback Anda.');

        $rating = $this->ticket->rating()->first();
        $this->assertSame(4, $rating->score);
        $this->assertSame('Cepat, terima kasih.', $rating->comment);
        $this->assertSame($this->mahasiswa->id, $rating->user_id);

        // Kedua kalinya ditolak.
        $this->actingAs($this->mahasiswa)->postJson("/tickets/{$this->ticket->id}/rating", ['score' => 1])
            ->assertStatus(409)->assertJsonPath('error.code', 'RATING_ALREADY_EXISTS');
        $this->assertSame(1, Rating::count());
        $this->assertSame(4, $this->ticket->rating()->first()->score);

        // Setelah dinilai, form hilang dan rating tampil.
        $this->actingAs($this->mahasiswa)->get("/history/{$this->ticket->id}")
            ->assertDontSee('Beri penilaian layanan')
            ->assertSee('Penilaian Anda')
            ->assertSee('4 dari 5 bintang')
            ->assertSee('Cepat, terima kasih.');
        $this->actingAs($this->mahasiswa)->get('/history')->assertSee('4 dari 5 bintang')->assertDontSee('Beri rating');
    }

    public function test_json_rating_and_optional_comment(): void
    {
        $this->actingAs($this->mahasiswa)->postJson("/tickets/{$this->ticket->id}/rating", ['score' => 5])
            ->assertCreated()
            ->assertJsonPath('data.score', 5)
            ->assertJsonPath('data.comment', null)
            ->assertJsonPath('message', 'Terima kasih atas feedback Anda.');
    }

    public function test_validation(): void
    {
        $post = fn (array $data) => $this->actingAs($this->mahasiswa)->postJson("/tickets/{$this->ticket->id}/rating", $data);

        $post([])->assertStatus(422)->assertJsonValidationErrors('score');
        $post(['score' => 0])->assertStatus(422)->assertJsonValidationErrors('score');
        $post(['score' => 6])->assertStatus(422)->assertJsonValidationErrors('score');
        $post(['score' => 'lima'])->assertStatus(422)->assertJsonValidationErrors('score');
        $post(['score' => 5, 'comment' => str_repeat('a', 501)])->assertStatus(422)->assertJsonValidationErrors('comment');
        $post(['score' => 5, 'comment' => str_repeat('a', 500)])->assertCreated();
    }

    public function test_only_resolved_tickets_can_be_rated(): void
    {
        foreach ([TicketStatus::Menunggu, TicketStatus::Diproses, TicketStatus::Ditolak] as $status) {
            $ticket = $this->ticketFor($this->mahasiswa, null, ['status' => $status, 'resolved_at' => $status === TicketStatus::Ditolak ? now() : null]);

            $this->actingAs($this->mahasiswa)->postJson("/tickets/{$ticket->id}/rating", ['score' => 5])
                ->assertStatus(409)->assertJsonPath('error.code', 'RATING_NOT_ALLOWED');
        }

        $this->assertSame(0, Rating::count());
    }

    public function test_only_the_owner_can_rate(): void
    {
        $this->actingAs(User::factory()->mahasiswa($this->fitk)->create())->post("/tickets/{$this->ticket->id}/rating", ['score' => 5])->assertForbidden();
        $this->actingAs($this->staff)->post("/tickets/{$this->ticket->id}/rating", ['score' => 5])->assertForbidden();

        $this->assertSame(0, Rating::count());
    }

    public function test_rating_is_shown_to_staff(): void
    {
        $this->actingAs($this->mahasiswa)->post("/tickets/{$this->ticket->id}/rating", ['score' => 3, 'comment' => 'Lumayan']);

        $this->actingAs($this->staff)->get("/staff/history/{$this->ticket->id}")
            ->assertOk()->assertSee('Rating dari pelapor')->assertSee('3 dari 5 bintang')->assertSee('Lumayan');
        $this->actingAs($this->staff)->get('/staff/history')->assertSee('3 dari 5 bintang');
    }
}
