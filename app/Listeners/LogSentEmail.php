<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\HelpdeskMail;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Str;

/**
 * [M6] Catat setiap email yang BENAR-BENAR terkirim di tabel notifications versi PRD (type = email).
 *
 * Event NotificationSent dikirim Laravel setelah channel mail selesai mengirim (di queue worker). Baris type = email
 * tidak tampil di lonceng (lonceng hanya membaca type = in_app, lihat User::inAppNotifications()), tetapi berguna
 * untuk menelusuri "apakah email untuk tiket ini sudah terkirim?" langsung dari database.
 */
class LogSentEmail
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'mail' || ! $event->notification instanceof HelpdeskMail || ! $event->notifiable instanceof User) {
            return;
        }

        // rescue(): emailnya SUDAH terkirim. Kalau pencatatan gagal, job tidak boleh dianggap gagal
        // (job gagal = dicoba ulang = email terkirim dua kali).
        rescue(fn () => Notification::create([
            'user_id' => $event->notifiable->id,
            'ticket_id' => $event->notification->ticket()?->id,
            'type' => NotificationType::Email,
            'subject' => Str::limit($event->notification->subject(), 197),
            'message' => 'Email terkirim ke '.$event->notifiable->email,
            'is_read' => true, // bukan untuk dibaca di lonceng
            'sent_at' => now(),
        ]));
    }
}
