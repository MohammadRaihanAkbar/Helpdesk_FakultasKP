<?php

namespace App\Listeners;

use App\Events\TicketPinged;
use App\Models\User;
use App\Notifications\TicketPingedMail;

/**
 * [M6] Email teguran Ping ke setiap Staff penerima (F-NTF-06, US-14).
 *
 * Listener ini "mendengarkan" event TicketPinged yang dikirim PingService sejak M4 (setelah ping_logs dan
 * notifikasi in-app tersimpan). Laravel menemukannya otomatis karena berada di app/Listeners dan parameter
 * handle() bertipe TicketPinged — PingService tidak perlu diubah sama sekali.
 *
 * Emailnya sendiri masuk antrean (TicketPingedMail implements ShouldQueue), jadi Admin tidak menunggu SMTP.
 */
class SendPingEmails
{
    public function handle(TicketPinged $event): void
    {
        $event->staff
            ->filter(fn (User $staff) => $staff->canReceiveEmail())
            ->each(fn (User $staff) => rescue(fn () => $staff->notify(
                new TicketPingedMail($event->ticket, $event->admin->name, $event->message)
            )));
    }
}
