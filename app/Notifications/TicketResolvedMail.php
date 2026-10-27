<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/** F-NTF-04: email ke pelapor saat tiket Selesai, dengan info bukti penyelesaian + link ke form rating. */
class TicketResolvedMail extends TicketMail
{
    public function subject(): string
    {
        return "Tiket {$this->ticket->ticket_number} selesai, beri penilaian";
    }

    public function toMail(User $notifiable): MailMessage
    {
        $proofs = $this->ticket->resolutionProofs()->count();
        $mail = $this->message($notifiable)
            ->line('Kabar baik! Tiket Anda sudah **selesai** ditangani Staff.');

        return $this->summary($mail)
            ->line($proofs > 0
                ? "Staff melampirkan {$proofs} bukti penyelesaian yang bisa Anda lihat di halaman tiket."
                : 'Detail penyelesaian bisa Anda lihat di halaman tiket.')
            ->line('Bagaimana pelayanan kami? Penilaian 1 sampai 5 bintang Anda membantu fakultas memperbaiki layanan.')
            ->action('Beri Penilaian', route('history.show', $this->ticket).'#rating');
    }
}
