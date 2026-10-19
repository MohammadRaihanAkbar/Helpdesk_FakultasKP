<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketStatus;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\CommentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Thread diskusi (US-04, F-TKU-05, F-TKS-08): balasan, label Staff, catatan internal, batas 20 pesan.
 * Staff penangan dibuat lewat factory (fitur ambil tiket baru ada di M3).
 */
class ThreadTest extends TicketTestCase
{
    private User $staff;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->staff($this->fitk)->create(['name' => 'Sari Staff']);
        $this->ticket = $this->ticketFor($this->mahasiswa, null, [
            'status' => TicketStatus::Diproses, 'handler_id' => $this->staff->id, 'assigned_at' => now(),
        ]);
    }

    private function addComments(int $count, bool $internal = false, ?User $author = null): void
    {
        foreach (range(1, $count) as $i) {
            Comment::create(['ticket_id' => $this->ticket->id, 'user_id' => ($author ?? $this->mahasiswa)->id, 'body' => "Pesan {$i}", 'is_internal_note' => $internal]);
        }
    }

    public function test_reporter_can_reply_with_attachment(): void
    {
        $this->actingAs($this->mahasiswa)->post("/tickets/{$this->ticket->id}/comments", [
            'body' => 'Ini screenshot tambahan.', 'attachments' => [UploadedFile::fake()->image('tambahan.png')],
        ])->assertRedirect(route('tickets.show', $this->ticket).'#thread');

        $comment = $this->ticket->comments()->firstOrFail();
        $this->assertSame('Ini screenshot tambahan.', $comment->body);
        $this->assertFalse($comment->is_internal_note);
        $this->assertSame($this->mahasiswa->id, $comment->user_id);
        $this->assertCount(1, $comment->attachments);
        $this->assertNull($comment->attachments->first()->ticket_id);
        Storage::disk('local')->assertExists($comment->attachments->first()->file_path);
    }

    public function test_reporter_cannot_post_internal_notes(): void
    {
        $this->actingAs($this->mahasiswa)->post("/tickets/{$this->ticket->id}/comments", ['body' => 'Coba', 'is_internal_note' => '1']);

        $this->assertFalse($this->ticket->comments()->firstOrFail()->is_internal_note);
    }

    public function test_reply_validation(): void
    {
        $this->actingAs($this->mahasiswa)->post("/tickets/{$this->ticket->id}/comments", ['body' => ''])->assertSessionHasErrors('body');
        $this->actingAs($this->mahasiswa)->post("/tickets/{$this->ticket->id}/comments", ['body' => str_repeat('a', 2001)])->assertSessionHasErrors('body');
        $this->actingAs($this->mahasiswa)->post("/tickets/{$this->ticket->id}/comments", [
            'body' => 'Ada lampiran', 'attachments' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('attachments.0');

        $this->assertSame(0, $this->ticket->comments()->count());
    }

    public function test_thread_shows_staff_label_counter_and_hides_internal_notes_from_reporter(): void
    {
        Comment::create(['ticket_id' => $this->ticket->id, 'user_id' => $this->staff->id, 'body' => 'Terima kasih laporannya.', 'is_internal_note' => false]);
        Comment::create(['ticket_id' => $this->ticket->id, 'user_id' => $this->staff->id, 'body' => 'RAHASIA: cek ke BAAK dulu', 'is_internal_note' => true]);
        Comment::create(['ticket_id' => $this->ticket->id, 'user_id' => $this->mahasiswa->id, 'body' => 'Baik, saya tunggu.', 'is_internal_note' => false]);

        $this->actingAs($this->mahasiswa)->get("/tickets/{$this->ticket->id}")
            ->assertOk()
            ->assertSeeInOrder(['Sari Staff', 'Staff', 'Terima kasih laporannya.', 'Anda', 'Baik, saya tunggu.'])
            ->assertDontSee('RAHASIA: cek ke BAAK dulu')
            ->assertDontSee('Catatan Internal')
            ->assertSee('2/20 pesan'); // catatan internal tidak dihitung
    }

    public function test_internal_note_attachment_is_not_downloadable_by_reporter(): void
    {
        $note = Comment::create(['ticket_id' => $this->ticket->id, 'user_id' => $this->staff->id, 'body' => 'internal', 'is_internal_note' => true]);
        Storage::disk('local')->put('attachments/rahasia.pdf', '%PDF');
        $attachment = $note->attachments()->create(['file_name' => 'rahasia.pdf', 'file_path' => 'attachments/rahasia.pdf', 'mime_type' => 'application/pdf', 'file_size' => 4]);

        $this->actingAs($this->mahasiswa)->get("/attachments/{$attachment->id}")->assertForbidden();
        $this->actingAs($this->staff)->get("/attachments/{$attachment->id}")->assertOk();
        $this->actingAs(User::factory()->admin($this->fitk)->create())->get("/attachments/{$attachment->id}")->assertOk();
    }

    public function test_thread_locks_at_20_public_messages(): void
    {
        $this->addComments(19);

        $this->actingAs($this->mahasiswa)->post("/tickets/{$this->ticket->id}/comments", ['body' => 'Pesan ke-20'])->assertSessionHasNoErrors();
        $this->assertSame(20, $this->ticket->publicComments()->count());

        $this->actingAs($this->mahasiswa)->postJson("/tickets/{$this->ticket->id}/comments", ['body' => 'Pesan ke-21'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'THREAD_LIMIT_REACHED');

        $this->actingAs($this->mahasiswa)->from("/tickets/{$this->ticket->id}")
            ->post("/tickets/{$this->ticket->id}/comments", ['body' => 'Pesan ke-21'])
            ->assertSessionHasErrors(['body' => 'Batas diskusi tercapai (20/20 pesan). Hubungi staf secara langsung jika diperlukan.']);

        $this->assertSame(20, $this->ticket->publicComments()->count());

        $this->actingAs($this->mahasiswa)->get("/tickets/{$this->ticket->id}")
            ->assertSee('20/20 pesan')
            ->assertSee('Batas diskusi tercapai (20/20 pesan). Hubungi staf secara langsung jika diperlukan.')
            ->assertDontSee('action="'.route('tickets.comments.store', $this->ticket).'"', false);
    }

    public function test_internal_notes_do_not_count_and_can_be_added_after_lock(): void
    {
        $this->addComments(5, internal: true, author: $this->staff);
        $this->addComments(19);

        // 19 publik + 5 internal: pelapor masih boleh mengirim pesan ke-20
        $this->actingAs($this->mahasiswa)->post("/tickets/{$this->ticket->id}/comments", ['body' => 'Masih bisa'])->assertSessionHasNoErrors();

        // Thread publik penuh, catatan internal staff tetap bisa (lewat service; halaman staff di M3).
        $note = app(CommentService::class)->post($this->ticket, $this->staff, 'Catatan setelah penuh', internal: true);
        $this->assertTrue($note->is_internal_note);
        $this->assertSame(20, $this->ticket->publicComments()->count());
    }

    public function test_first_public_reply_from_handler_sets_first_responded_at(): void
    {
        $this->assertNull($this->ticket->first_responded_at);

        app(CommentService::class)->post($this->ticket, $this->staff, 'Catatan dulu', internal: true);
        $this->assertNull($this->ticket->fresh()->first_responded_at);

        app(CommentService::class)->post($this->ticket, $this->staff, 'Halo, kami cek ya.');
        $this->assertNotNull($this->ticket->fresh()->first_responded_at);
    }

    public function test_closed_ticket_cannot_be_replied(): void
    {
        $this->ticket->update(['status' => TicketStatus::Selesai, 'resolved_at' => now()]);

        $this->actingAs($this->mahasiswa)->postJson("/tickets/{$this->ticket->id}/comments", ['body' => 'Halo?'])
            ->assertStatus(409)->assertJsonPath('error.code', 'THREAD_CLOSED');

        $this->assertSame(0, $this->ticket->comments()->count());
    }

    public function test_reporter_can_reply_while_ticket_is_still_waiting(): void
    {
        $waiting = $this->ticketFor($this->mahasiswa);

        $this->actingAs($this->mahasiswa)->post("/tickets/{$waiting->id}/comments", ['body' => 'Info tambahan'])->assertSessionHasNoErrors();
        $this->assertSame(1, $waiting->comments()->count());
    }

    public function test_other_users_cannot_reply(): void
    {
        $other = User::factory()->mahasiswa($this->fitk)->create();

        $this->actingAs($other)->post("/tickets/{$this->ticket->id}/comments", ['body' => 'Menyusup'])->assertForbidden();
        $this->actingAs($this->staff)->post("/tickets/{$this->ticket->id}/comments", ['body' => 'Lewat route pelapor'])->assertForbidden();
        $this->actingAs(User::factory()->admin($this->fitk)->create())->post("/tickets/{$this->ticket->id}/comments", ['body' => 'Admin'])->assertForbidden();

        $this->assertSame(0, $this->ticket->comments()->count());
    }
}
