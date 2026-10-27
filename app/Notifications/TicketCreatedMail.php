<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/** F-NTF-01: email ke pelapor saat tiket berhasil dibuat (berisi nomor tiket). */
class TicketCreatedMail extends TicketMail
{
    public function subject(): string
    {
        return "Tiket {$this->ticket->ticket_number} berhasil dibuat";
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = $this->message($notifiable)
            ->line('Tiket Anda sudah kami terima dan masuk ke antrean Staff fakultas tujuan.');

        return $this->summary($mail)
            ->line("Target penyelesaian (SLA): **{$this->ticket->sla_target_hours} jam** sejak tiket dibuat.")
            ->action('Lihat Tiket', $this->ticketUrl($notifiable, $this->ticket))
            ->line('Simpan nomor tiket ini. Kabar berikutnya (diproses, balasan Staff, selesai) juga dikirim ke email ini.');
    }
}
