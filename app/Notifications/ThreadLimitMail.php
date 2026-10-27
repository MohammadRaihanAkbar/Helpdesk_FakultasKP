<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/** F-NTF-07: thread tiket mencapai batas 20 pesan publik — ke pelapor dan Staff penangan. */
class ThreadLimitMail extends TicketMail
{
    public function subject(): string
    {
        return "Thread {$this->ticket->ticket_number} mencapai batas ".Ticket::THREAD_LIMIT.' pesan';
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = $this->message($notifiable)
            ->line('Diskusi tiket ini sudah mencapai **'.Ticket::THREAD_LIMIT.' pesan publik**, jadi pesan baru tidak bisa dikirim lagi.');

        return $this->summary($mail)
            ->line($notifiable->isStaff()
                ? 'Silakan selesaikan atau tutup tiket ini. Catatan internal tetap bisa ditulis.'
                : 'Staff penangan akan menyelesaikan atau menutup tiket ini.')
            ->action('Lihat Tiket', $this->ticketUrl($notifiable, $this->ticket));
    }
}
