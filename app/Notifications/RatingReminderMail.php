<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Pengingat rating (M6): tiket Selesai yang belum dinilai pelapor. Dikirim perintah
 * `php artisan helpdesk:pengingat-rating` (dijadwalkan tiap jam), lihat RatingReminderService.
 */
class RatingReminderMail extends TicketMail
{
    public function __construct(Ticket $ticket, public int $number)
    {
        parent::__construct($ticket);
    }

    public function subject(): string
    {
        return "Pengingat: beri penilaian tiket {$this->ticket->ticket_number}";
    }

    public function toMail(User $notifiable): MailMessage
    {
        $resolved = $this->ticket->resolved_at?->translatedFormat('j F Y');
        $mail = $this->message($notifiable)
            ->line('Tiket Anda sudah selesai'.($resolved ? " pada {$resolved}" : '').', tetapi belum diberi penilaian.');

        return $this->summary($mail)
            ->line('Penilaian hanya butuh beberapa detik (1–5 bintang, komentar boleh dikosongkan) dan sangat membantu fakultas memperbaiki layanan.')
            ->action('Beri Penilaian', route('history.show', $this->ticket).'#rating')
            ->line('Pengingat berhenti otomatis setelah Anda memberi penilaian.');
    }
}
