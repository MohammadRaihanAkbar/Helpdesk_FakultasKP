<?php

namespace App\Notifications;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * F-NTF-02: email ke pelapor saat status tiket berubah menjadi Diproses atau Ditolak.
 * (Selesai punya email sendiri dengan link rating: TicketResolvedMail, F-NTF-04.)
 *
 * Status, nama Staff, dan alasan disimpan SAAT peristiwa terjadi, karena email bisa saja baru dikirim beberapa
 * detik/menit kemudian oleh queue worker, ketika status tiketnya sudah berubah lagi.
 */
class TicketStatusChangedMail extends TicketMail
{
    public TicketStatus $status;

    public ?string $handlerName;

    public ?string $reason;

    public function __construct(Ticket $ticket)
    {
        parent::__construct($ticket);

        $this->status = $ticket->status;
        $this->handlerName = $ticket->handler?->name;
        $this->reason = $ticket->rejection_reason;
    }

    public function subject(): string
    {
        return match ($this->status) {
            TicketStatus::Diproses => "Tiket {$this->ticket->ticket_number} sedang diproses",
            TicketStatus::Ditolak => "Tiket {$this->ticket->ticket_number} ditolak",
            default => "Status tiket {$this->ticket->ticket_number}: {$this->status->label()}",
        };
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = $this->message($notifiable);

        if ($this->status === TicketStatus::Ditolak) {
            $mail->line('Mohon maaf, tiket Anda **ditolak** oleh Staff fakultas.')
                ->line(self::block(['Alasan' => (string) $this->reason]));
        } else {
            $mail->line('Tiket Anda sekarang **'.mb_strtolower($this->status->label()).'**'
                .($this->handlerName ? " oleh {$this->handlerName}" : '').'.');
        }

        $this->summary($mail)->action('Lihat Tiket', $this->ticketUrl($notifiable, $this->ticket));

        if ($this->status === TicketStatus::Ditolak && $this->ticket->isRejectedForWrongTarget()) {
            $mail->line('Tiket ini salah fakultas/kategori. Anda bisa menekan **Buat Ulang Tiket** di halaman tiket untuk mengirimnya ke tujuan yang benar.');
        }

        return $mail;
    }
}
