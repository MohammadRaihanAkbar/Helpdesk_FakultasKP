<?php

namespace Tests\Feature\Notifications;

use App\Models\Comment;
use App\Models\Notification;
use App\Models\Ticket;
use Tests\Feature\Staff\StaffTestCase;

/**
 * Notifikasi in-app (lonceng) alur tiket, M5 — TicketNotifier (F-NTF-01..05, F-NTF-07, US-02).
 * Email sengaja ditunda, jadi semua notifikasi di sini adalah baris notifications type = in_app.
 */
class InAppNotificationTest extends StaffTestCase
{
    private function subjects($user): array
    {
        return $user->inAppNotifications()->orderBy('id')->pluck('subject')->all();
    }

    public function test_new_ticket_notifies_the_reporter_with_the_ticket_number(): void
    {
        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload())->assertRedirect();
        $ticket = Ticket::firstOrFail();

        $this->assertSame(["Tiket {$ticket->ticket_number} berhasil dibuat"], $this->subjects($this->mahasiswa));
        $this->actingAs($this->mahasiswa)->get('/notifications')->assertOk()->assertSee("Tiket {$ticket->ticket_number} berhasil dibuat");
    }

    public function test_status_changes_are_written_and_resolved_links_to_the_rating_form(): void
    {
        $ticket = $this->waiting();

        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/assign");
        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/status", ['status' => 'selesai', 'proofs' => [$this->png()]]);

        $this->assertSame(["Tiket {$ticket->ticket_number} sedang diproses", "Tiket {$ticket->ticket_number} selesai"], $this->subjects($this->mahasiswa));

        $resolved = $this->mahasiswa->inAppNotifications()->orderByDesc('id')->first();
        $this->actingAs($this->mahasiswa)->patch("/notifications/{$resolved->id}/read")
            ->assertRedirect(route('history.show', $ticket).'#rating');
        $this->assertTrue($resolved->fresh()->is_read);
    }

    public function test_double_click_on_claim_writes_one_notification(): void
    {
        $ticket = $this->waiting();

        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/assign");
        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/assign");

        $this->assertCount(1, $this->subjects($this->mahasiswa));
    }

    public function test_rejection_notification_contains_the_reason(): void
    {
        $ticket = $this->inProgress();

        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/status", [
            'status' => 'ditolak', 'reason' => 'salah_tujuan', 'reason_note' => 'seharusnya ke FEBI',
        ]);

        $notification = $this->mahasiswa->inAppNotifications()->sole();
        $this->assertSame("Tiket {$ticket->ticket_number} ditolak", $notification->subject);
        $this->assertStringContainsString('Salah fakultas/kategori', $notification->message);
    }

    public function test_staff_reply_shows_as_new_reply_on_reporter_dashboard(): void
    {
        $ticket = $this->inProgress();

        $this->actingAs($this->akademik)->post("/staff/tickets/{$ticket->id}/comments", ['body' => 'Mohon kirim foto KRS Anda.']);

        $this->assertSame(1, $this->mahasiswa->inAppNotifications()->where('is_read', false)->count());

        $this->actingAs($this->mahasiswa)->get('/dashboard')
            ->assertOk()
            ->assertSee('Notifikasi baru (1 belum dibaca)')
            ->assertSee("Balasan baru dari Staff: {$ticket->ticket_number}")
            ->assertSee('Mohon kirim foto KRS Anda.')
            ->assertSeeInOrder([$ticket->ticket_number, 'Balasan baru']);
    }

    public function test_opening_the_ticket_marks_its_notifications_as_read(): void
    {
        $ticket = $this->inProgress();
        $this->actingAs($this->akademik)->post("/staff/tickets/{$ticket->id}/comments", ['body' => 'Sudah kami cek.']);

        $this->actingAs($this->mahasiswa)->get("/tickets/{$ticket->id}")->assertOk();

        $this->assertSame(0, $this->mahasiswa->inAppNotifications()->where('is_read', false)->count());
        $this->actingAs($this->mahasiswa)->get('/dashboard')->assertDontSee('Notifikasi baru')->assertDontSee('Balasan baru');
    }

    public function test_internal_note_and_reporter_own_reply_create_no_notification(): void
    {
        $ticket = $this->inProgress();

        $this->actingAs($this->akademik)->post("/staff/tickets/{$ticket->id}/comments", ['body' => 'Catatan internal', 'is_internal_note' => 1]);
        $this->actingAs($this->mahasiswa)->post("/tickets/{$ticket->id}/comments", ['body' => 'Balasan saya sendiri']);

        $this->assertSame(0, Notification::count());
    }

    public function test_twentieth_public_message_notifies_reporter_and_handler_once(): void
    {
        $ticket = $this->inProgress();
        foreach (range(1, Ticket::THREAD_LIMIT - 1) as $i) {
            Comment::create(['ticket_id' => $ticket->id, 'user_id' => $i % 2 ? $this->mahasiswa->id : $this->akademik->id, 'body' => "Pesan {$i}", 'is_internal_note' => false]);
        }

        $this->actingAs($this->mahasiswa)->post("/tickets/{$ticket->id}/comments", ['body' => 'Pesan ke-20'])->assertRedirect();
        // Catatan internal setelahnya tetap boleh, tetapi tidak memicu notifikasi batas lagi.
        $this->actingAs($this->akademik)->post("/staff/tickets/{$ticket->id}/comments", ['body' => 'Catatan internal', 'is_internal_note' => 1]);

        $limit = "Thread {$ticket->ticket_number} mencapai batas 20 pesan";
        $this->assertSame([$limit], $this->subjects($this->mahasiswa));
        $this->assertSame([$limit], $this->subjects($this->akademik));

        // Di dashboard Staff, notifikasi ini tidak tampil sebagai "Teguran dari Admin" (kotak itu khusus Ping).
        $this->actingAs($this->akademik)->get('/staff/dashboard')->assertOk()->assertDontSee('Teguran dari Admin');
    }

    public function test_notification_failure_does_not_cancel_the_ticket(): void
    {
        Notification::creating(fn () => throw new \RuntimeException('tabel notifikasi bermasalah'));

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Ticket::count());
    }
}
