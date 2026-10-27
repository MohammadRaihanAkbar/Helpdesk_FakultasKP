<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Induk email yang membicarakan SATU tiket. Menyimpan tiketnya + helper baris ringkasan tiket.
 *
 * Model Ticket disimpan ke antrean sebagai id saja (SerializesModels) dan diambil ulang dari database saat
 * email benar-benar dikirim. Karena itu nilai yang bisa berubah sebelum dikirim (status, alasan) disimpan
 * terpisah di class turunannya.
 */
abstract class TicketMail extends HelpdeskMail
{
    public function __construct(public Ticket $ticket)
    {
        parent::__construct();
    }

    public function ticket(): ?Ticket
    {
        return $this->ticket;
    }

    /** Baris ringkasan: nomor, judul, kategori, fakultas. */
    protected function summary(MailMessage $mail): MailMessage
    {
        $ticket = $this->ticket->loadMissing('category.parent', 'faculty');

        return $mail->line(self::block([
            'Nomor tiket' => $ticket->ticket_number,
            'Judul' => $ticket->title,
            'Kategori' => $ticket->category->pathLabel(),
            'Fakultas tujuan' => "{$ticket->faculty->code} · {$ticket->faculty->name}",
        ]));
    }
}
