<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Enums\TicketStatus;
use App\Models\Comment;
use App\Models\Notification as NotificationLog;
use App\Models\Ticket;
use App\Notifications\HelpdeskMail;
use App\Notifications\RatingReminderMail;
use App\Notifications\StaffRepliedMail;
use App\Notifications\ThreadLimitMail;
use App\Notifications\TicketCreatedMail;
use App\Notifications\TicketPingedMail;
use App\Notifications\TicketResolvedMail;
use App\Notifications\TicketStatusChangedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\AdminMonitoring\AdminTestCase;

/**
 * Email notifikasi M6 (F-NTF-01..04, 06, 07): peristiwa mana yang mengirim email, ke siapa,
 * dan siapa yang TIDAK dikirimi (email pengganti SSO). Notifikasi in-app tetap jalan seperti M5.
 */
class EmailNotificationTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_new_ticket_sends_email_with_the_ticket_number_to_the_reporter(): void
    {
        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload())->assertRedirect();
        $ticket = Ticket::firstOrFail();

        Notification::assertSentTo($this->mahasiswa, TicketCreatedMail::class,
            fn (TicketCreatedMail $mail, array $channels) => $mail->ticket->is($ticket) && $channels === ['mail']
                && str_contains($mail->subject(), $ticket->ticket_number));
        // In-app tetap ditulis (M5).
        $this->assertSame(1, $this->mahasiswa->inAppNotifications()->count());
    }

    public function test_claim_resolve_and_reject_send_status_emails(): void
    {
        $ticket = $this->waiting();
        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/assign");
        Notification::assertSentTo($this->mahasiswa, TicketStatusChangedMail::class,
            fn (TicketStatusChangedMail $mail) => $mail->status === TicketStatus::Diproses && $mail->handlerName === 'Sari Akademik');

        $this->actingAs($this->akademik)->patch("/staff/tickets/{$ticket->id}/status", ['status' => 'selesai', 'proofs' => [$this->png()]]);
        Notification::assertSentTo($this->mahasiswa, TicketResolvedMail::class, fn (TicketResolvedMail $mail) => $mail->ticket->is($ticket));

        $other = $this->inProgress();
        $this->actingAs($this->akademik)->patch("/staff/tickets/{$other->id}/status", [
            'status' => 'ditolak', 'reason' => 'salah_tujuan', 'reason_note' => 'seharusnya ke FEBI',
        ]);
        Notification::assertSentTo($this->mahasiswa, TicketStatusChangedMail::class,
            fn (TicketStatusChangedMail $mail) => $mail->status === TicketStatus::Ditolak && str_contains((string) $mail->reason, 'seharusnya ke FEBI'));
    }

    public function test_staff_reply_sends_email_but_internal_notes_and_own_replies_do_not(): void
    {
        $ticket = $this->inProgress();

        $this->actingAs($this->akademik)->post("/staff/tickets/{$ticket->id}/comments", ['body' => 'Catatan internal', 'is_internal_note' => 1]);
        $this->actingAs($this->mahasiswa)->post("/tickets/{$ticket->id}/comments", ['body' => 'Balasan saya sendiri']);
        Notification::assertNothingSent();

        $this->actingAs($this->akademik)->post("/staff/tickets/{$ticket->id}/comments", ['body' => 'Sudah kami teruskan ke Dosen PA.']);
        Notification::assertSentTo($this->mahasiswa, StaffRepliedMail::class,
            fn (StaffRepliedMail $mail) => $mail->staffName === 'Sari Akademik' && $mail->excerpt === 'Sudah kami teruskan ke Dosen PA.');
        Notification::assertNotSentTo($this->akademik, StaffRepliedMail::class);
    }

    public function test_thread_limit_emails_reporter_and_handler(): void
    {
        $ticket = $this->inProgress();
        foreach (range(1, Ticket::THREAD_LIMIT - 1) as $i) {
            Comment::create(['ticket_id' => $ticket->id, 'user_id' => $i % 2 ? $this->mahasiswa->id : $this->akademik->id, 'body' => "Pesan {$i}", 'is_internal_note' => false]);
        }

        $this->actingAs($this->mahasiswa)->post("/tickets/{$ticket->id}/comments", ['body' => 'Pesan ke-20'])->assertRedirect();

        Notification::assertSentTo($this->mahasiswa, ThreadLimitMail::class);
        Notification::assertSentTo($this->akademik, ThreadLimitMail::class);
    }

    public function test_ping_emails_every_pinged_staff_only(): void
    {
        $ticket = $this->ticket(['created' => now()->subHours(30)]);

        $this->actingAs($this->admin)->postJson("/admin/tickets/{$ticket->id}/ping", ['message' => 'Tolong segera.'])->assertOk();

        foreach ([$this->akademik, $this->akademik2] as $staff) {
            Notification::assertSentTo($staff, TicketPingedMail::class,
                fn (TicketPingedMail $mail) => $mail->adminName === 'Admin FITK' && $mail->adminMessage === 'Tolong segera.');
        }
        foreach ([$this->nonaktif, $this->umum, $this->staffFebi, $this->mahasiswa] as $other) {
            Notification::assertNotSentTo($other, TicketPingedMail::class);
        }
    }

    public function test_users_with_the_sso_placeholder_email_only_get_in_app_notifications(): void
    {
        $this->mahasiswa->update(['email' => $this->mahasiswa->nim.'@'.config('sso.email_fallback_domain')]);

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload())->assertRedirect();

        Notification::assertNothingSent();
        $this->assertFalse($this->mahasiswa->fresh()->canReceiveEmail());
        $this->assertNull($this->mahasiswa->fresh()->routeNotificationForMail());
        $this->assertSame(1, $this->mahasiswa->inAppNotifications()->count());
    }

    public function test_email_can_be_switched_off_in_config(): void
    {
        config(['helpdesk.email_notifications' => false]);

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload())->assertRedirect();

        Notification::assertNothingSent();
        $this->assertSame(1, $this->mahasiswa->inAppNotifications()->count());
    }

    public function test_emails_are_queued_with_three_tries_and_backoff(): void
    {
        $ticket = $this->ticket(['status' => TicketStatus::Selesai]);

        foreach ([new TicketCreatedMail($ticket), new TicketResolvedMail($ticket), new RatingReminderMail($ticket, 1)] as $mail) {
            $this->assertInstanceOf(ShouldQueue::class, $mail);
            $this->assertInstanceOf(HelpdeskMail::class, $mail);
            $this->assertSame(3, $mail->tries);
            $this->assertSame([60, 300], $mail->backoff());
            $this->assertTrue($mail->afterCommit);
            $this->assertSame(['mail'], $mail->via($this->mahasiswa));
        }
    }

    public function test_emails_render_in_indonesian_with_the_right_links(): void
    {
        $ticket = $this->ticket(['status' => TicketStatus::Selesai]);

        $resolved = (string) (new TicketResolvedMail($ticket))->toMail($this->mahasiswa)->render();
        foreach ([$ticket->ticket_number, $ticket->title, 'Beri Penilaian', route('history.show', $ticket).'#rating', 'Halo, Ahmad!', 'Helpdesk Fakultas Terpadu', 'Mohon tidak membalas email ini'] as $text) {
            $this->assertStringContainsString(e($text), $resolved);
        }
        $this->assertStringNotContainsString('Regards', $resolved);

        $ping = (string) (new TicketPingedMail($this->ticket(['created' => now()->subHours(30)]), 'Admin FITK', null))->toMail($this->akademik)->render();
        $this->assertStringContainsString('Mohon tiket ini segera ditindaklanjuti.', $ping);
        $this->assertStringContainsString('/staff/tickets/', $ping);
        $this->assertStringNotContainsString('Ahmad Mahasiswa', $ping); // identitas pelapor tidak ikut di email Ping
    }

    public function test_sent_email_is_logged_as_email_type_without_appearing_in_the_bell(): void
    {
        Notification::swap(new ChannelManager($this->app)); // kirim sungguhan lewat mailer "array"

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload())->assertRedirect();
        $ticket = Ticket::firstOrFail();

        $log = NotificationLog::where('type', NotificationType::Email)->firstOrFail();
        $this->assertSame([$this->mahasiswa->id, $ticket->id], [$log->user_id, $log->ticket_id]);
        $this->assertStringContainsString($ticket->ticket_number, $log->subject);
        $this->assertSame(1, $this->mahasiswa->inAppNotifications()->count());
    }
}
