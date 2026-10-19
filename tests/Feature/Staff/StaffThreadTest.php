<?php

namespace Tests\Feature\Staff;

use App\Models\Comment;
use App\Models\Ticket;

/**
 * Detail tiket & thread dari sisi Staff (US-08, F-TKS-04, F-TKS-08):
 * hanya penangan yang membalas/menulis catatan internal; catatan internal tidak terlihat pelapor dan
 * tidak dihitung batas 20; balasan publik pertama penangan mengisi first_responded_at sekali saja.
 */
class StaffThreadTest extends StaffTestCase
{
    public function test_handler_sees_reply_form_internal_note_option_and_close_actions(): void
    {
        $ticket = $this->inProgress();

        $this->actingAs($this->akademik)->get("/staff/tickets/{$ticket->id}")
            ->assertOk()
            ->assertSee('Thread Diskusi')
            ->assertSee('Catatan Internal')
            ->assertSee('Tutup tiket')
            ->assertSee('Tandai Selesai')
            ->assertSee('Tolak Tiket')
            ->assertSee(Ticket::REJECTION_WRONG_TARGET)
            ->assertSee('Ahmad Mahasiswa');
    }

    public function test_other_specialist_can_only_view(): void
    {
        $ticket = $this->inProgress($this->akademik);

        $this->actingAs($this->akademik2)->get("/staff/tickets/{$ticket->id}")
            ->assertOk()
            ->assertSee('Anda hanya dapat melihat')
            ->assertDontSee('Tutup tiket')
            ->assertDontSee(route('staff.tickets.comments.store', $ticket));

        $this->actingAs($this->akademik2)->post("/staff/tickets/{$ticket->id}/comments", ['body' => 'Ikut balas'])->assertForbidden();
        $this->actingAs($this->akademik2)->post("/staff/tickets/{$ticket->id}/comments", ['body' => 'Catatan', 'is_internal_note' => '1'])->assertForbidden();
        $this->actingAs($this->akademik2)->patch("/staff/tickets/{$ticket->id}/status", ['status' => 'ditolak', 'reason' => 'duplikat'])->assertForbidden();
        $this->assertSame(0, $ticket->comments()->count());
    }

    public function test_staff_of_other_faculty_or_group_cannot_view(): void
    {
        $ticket = $this->inProgress();

        $this->actingAs($this->staffFebi)->get("/staff/tickets/{$ticket->id}")->assertForbidden();
        $this->actingAs($this->umum)->get("/staff/tickets/{$ticket->id}")->assertForbidden();
        $this->actingAs($this->tanpaSpesialisasi)->get("/staff/tickets/{$ticket->id}")->assertForbidden();
        $this->actingAs($this->mahasiswa)->get("/staff/tickets/{$ticket->id}")->assertForbidden(); // pelapor: bukan halaman staff
    }

    public function test_handler_reply_and_internal_note(): void
    {
        $ticket = $this->inProgress();

        $this->actingAs($this->akademik)->post("/staff/tickets/{$ticket->id}/comments", ['body' => 'Mohon kirim foto KRS.'])
            ->assertRedirect("/staff/tickets/{$ticket->id}#thread")
            ->assertSessionHas('status', 'Pesan terkirim ke pelapor.');
        $this->actingAs($this->akademik)->post("/staff/tickets/{$ticket->id}/comments", [
            'body' => 'RAHASIA: dosen PA sedang cuti.', 'is_internal_note' => '1', 'attachments' => [$this->pdf('memo.pdf')],
        ])->assertSessionHas('status', 'Catatan internal tersimpan (tidak terlihat oleh pelapor).');

        $this->assertSame(1, $ticket->publicComments()->count());
        $this->assertSame(1, $ticket->comments()->where('is_internal_note', true)->count());

        // Staff (penangan & sesama spesialis) melihat catatan internal; counter hanya pesan publik.
        $this->actingAs($this->akademik2)->get("/staff/tickets/{$ticket->id}")
            ->assertSee('RAHASIA: dosen PA sedang cuti.')->assertSee('memo.pdf')->assertSee('1/20 pesan');

        // Pelapor tidak pernah melihatnya.
        $this->actingAs($this->mahasiswa)->get("/tickets/{$ticket->id}")
            ->assertSee('Mohon kirim foto KRS.')->assertSee('Staff')
            ->assertDontSee('RAHASIA')->assertDontSee('memo.pdf')->assertSee('1/20 pesan');
    }

    public function test_first_public_reply_sets_first_responded_at_once(): void
    {
        $ticket = $this->inProgress();

        // Catatan internal BUKAN respons ke pelapor.
        $this->actingAs($this->akademik)->post("/staff/tickets/{$ticket->id}/comments", ['body' => 'catatan', 'is_internal_note' => '1']);
        $this->assertNull($ticket->fresh()->first_responded_at);

        $this->travelTo(now()->addHour());
        $this->actingAs($this->akademik)->post("/staff/tickets/{$ticket->id}/comments", ['body' => 'Balasan pertama']);
        $first = $ticket->fresh()->first_responded_at;
        $this->assertNotNull($first);

        $this->travelTo(now()->addHours(3));
        $this->actingAs($this->akademik)->post("/staff/tickets/{$ticket->id}/comments", ['body' => 'Balasan kedua']);
        $this->assertEquals($first, $ticket->fresh()->first_responded_at, 'tidak boleh tertimpa balasan berikutnya');

        $this->actingAs($this->mahasiswa)->get("/tickets/{$ticket->id}")->assertSee('Respons pertama Staff');
    }

    public function test_reporter_reply_does_not_set_first_responded_at(): void
    {
        $ticket = $this->inProgress();

        $this->actingAs($this->mahasiswa)->post("/tickets/{$ticket->id}/comments", ['body' => 'Ada kabar?']);

        $this->assertNull($ticket->fresh()->first_responded_at);
    }

    public function test_full_thread_locks_public_reply_but_allows_internal_note(): void
    {
        $ticket = $this->inProgress();
        foreach (range(1, Ticket::THREAD_LIMIT) as $i) {
            Comment::create(['ticket_id' => $ticket->id, 'user_id' => $i % 2 ? $this->mahasiswa->id : $this->akademik->id, 'body' => "Pesan {$i}", 'is_internal_note' => false]);
        }

        $this->actingAs($this->akademik)->get("/staff/tickets/{$ticket->id}")
            ->assertSee('20/20 pesan')
            ->assertSee('Pesan publik terkunci, tetapi Catatan Internal tetap dapat ditambahkan.')
            ->assertSee('name="is_internal_note" value="1"', false);

        $this->actingAs($this->akademik)->postJson("/staff/tickets/{$ticket->id}/comments", ['body' => 'Pesan ke-21'])
            ->assertStatus(409)->assertJsonPath('error.code', 'THREAD_LIMIT_REACHED');

        $this->actingAs($this->akademik)->postJson("/staff/tickets/{$ticket->id}/comments", ['body' => 'Catatan setelah penuh', 'is_internal_note' => true])
            ->assertCreated()->assertJsonPath('meta.public_count', 20);
    }

    public function test_sensitive_reporter_name_is_masked_in_thread_for_non_handler(): void
    {
        $ticket = $this->waiting($this->cat('Integritas Dosen'));
        Comment::create(['ticket_id' => $ticket->id, 'user_id' => $this->mahasiswa->id, 'body' => 'Tambahan info dari pelapor', 'is_internal_note' => false]);

        $this->actingAs($this->dekanat)->get("/staff/tickets/{$ticket->id}")
            ->assertSee('Tambahan info dari pelapor')
            ->assertSee('Pelapor (identitas dirahasiakan)')
            ->assertDontSee('Ahmad Mahasiswa');
    }

    public function test_admin_cannot_post_to_staff_thread(): void
    {
        $ticket = $this->inProgress();

        $this->actingAs($this->admin)->post("/staff/tickets/{$ticket->id}/comments", ['body' => 'x'])->assertForbidden();
    }
}
