<?php

namespace Tests\Feature\Notifications;

use App\Enums\TicketStatus;
use App\Models\Rating;
use App\Models\Ticket;
use App\Notifications\RatingReminderMail;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\AdminMonitoring\AdminTestCase;

/**
 * Pengingat rating M6 (php artisan helpdesk:pengingat-rating): waktu, batas jumlah, berhenti setelah dirating,
 * tidak untuk tiket Ditolak, dan idempotent.
 */
class RatingReminderTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(now()->startOfHour());
    }

    /** Tiket Selesai yang resolved_at-nya $hoursAgo jam lalu. */
    private function resolvedAgo(float $hoursAgo, array $o = []): Ticket
    {
        return $this->ticket($o + ['status' => TicketStatus::Selesai, 'created' => now()->subMinutes((int) round(($hoursAgo + 5) * 60)), 'resolveAfter' => 5]);
    }

    private function remind(array $options = []): void
    {
        $this->artisan('helpdesk:pengingat-rating', $options)->assertSuccessful();
    }

    public function test_first_reminder_comes_24_hours_after_resolution(): void
    {
        $early = $this->resolvedAgo(23);
        $due = $this->resolvedAgo(25);

        $this->remind();

        Notification::assertSentTo($this->mahasiswa, RatingReminderMail::class, fn (RatingReminderMail $mail) => $mail->ticket->is($due) && $mail->number === 1);
        Notification::assertSentTimes(RatingReminderMail::class, 1);
        $this->assertSame([0, 1], [$early->fresh()->rating_reminder_count, $due->fresh()->rating_reminder_count]);
        $this->assertSame(["Pengingat: beri penilaian tiket {$due->ticket_number}"],
            $this->mahasiswa->inAppNotifications()->pluck('subject')->all());

        // Notifikasi lonceng langsung membuka form rating.
        $item = $this->mahasiswa->inAppNotifications()->first();
        $this->actingAs($this->mahasiswa)->patch("/notifications/{$item->id}/read")->assertRedirect(route('history.show', $due).'#rating');
    }

    public function test_running_twice_does_not_send_the_same_reminder_again(): void
    {
        $this->resolvedAgo(30);

        $this->remind();
        $this->remind();

        Notification::assertSentTimes(RatingReminderMail::class, 1);
    }

    public function test_second_reminder_after_48_hours_and_never_more_than_two(): void
    {
        $ticket = $this->resolvedAgo(25);
        $this->remind();

        $this->travel(47)->hours();
        $this->remind();
        Notification::assertSentTimes(RatingReminderMail::class, 1);

        $this->travel(1)->hours();
        $this->remind();
        Notification::assertSentTimes(RatingReminderMail::class, 2);
        $this->assertSame(2, $ticket->fresh()->rating_reminder_count);

        $this->travel(5)->days();
        $this->remind();
        Notification::assertSentTimes(RatingReminderMail::class, 2);
    }

    public function test_reminders_stop_once_the_ticket_is_rated(): void
    {
        $ticket = $this->resolvedAgo(25);
        $this->remind();

        Rating::create(['ticket_id' => $ticket->id, 'user_id' => $this->mahasiswa->id, 'score' => 5]);
        $this->travel(3)->days();
        $this->remind();

        Notification::assertSentTimes(RatingReminderMail::class, 1);
    }

    public function test_rejected_rated_and_old_tickets_are_never_reminded(): void
    {
        $this->ticket(['status' => TicketStatus::Ditolak, 'created' => now()->subDays(3)]);
        $this->resolvedAgo(30, ['rating' => 4]);
        $this->resolvedAgo(24 * 20); // selesai 20 hari lalu (> 14 hari)

        $this->remind();

        Notification::assertNothingSent();
    }

    public function test_reporter_without_real_email_gets_the_in_app_reminder_only(): void
    {
        $this->mahasiswa->update(['email' => $this->mahasiswa->nim.'@'.config('sso.email_fallback_domain')]);
        $ticket = $this->resolvedAgo(25);

        $this->remind();

        Notification::assertNothingSent();
        $this->assertSame(1, $ticket->fresh()->rating_reminder_count);
        $this->assertSame(1, $this->mahasiswa->inAppNotifications()->count());
    }

    public function test_demo_options_force_and_preview(): void
    {
        $ticket = $this->resolvedAgo(1);

        $this->artisan('helpdesk:pengingat-rating', ['--lihat' => true, '--paksa' => true])
            ->expectsOutputToContain('1 tiket akan diingatkan.')
            ->assertSuccessful();
        Notification::assertNothingSent();

        $this->remind(['--paksa' => true]);
        $this->remind(['--paksa' => true]);
        $this->remind(['--paksa' => true]);
        Notification::assertSentTimes(RatingReminderMail::class, 2); // --paksa tetap mematuhi batas 2 pengingat
        $this->assertSame(2, $ticket->fresh()->rating_reminder_count);
    }

    public function test_limits_come_from_config(): void
    {
        config(['helpdesk.rating_reminder.first_after_hours' => 2, 'helpdesk.rating_reminder.max_reminders' => 1]);
        $this->resolvedAgo(3);

        $this->remind();
        $this->travel(4)->days();
        $this->remind();

        Notification::assertSentTimes(RatingReminderMail::class, 1);
    }

    public function test_command_is_scheduled_hourly(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'helpdesk:pengingat-rating'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }
}
