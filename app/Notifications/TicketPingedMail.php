<?php

namespace App\Notifications;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Format;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * F-NTF-06: email teguran Ping dari Admin Fakultas ke Staff (US-14). Dikirim listener SendPingEmails
 * setelah ping_logs + notifikasi in-app tersimpan (PingService, M4).
 *
 * Seperti notifikasi in-app Ping, identitas pelapor TIDAK disertakan (aman untuk kategori sensitif).
 */
class TicketPingedMail extends TicketMail
{
    public string $facultyCode;

    public string $waiting;

    public function __construct(Ticket $ticket, public string $adminName, public ?string $adminMessage)
    {
        parent::__construct($ticket);

        $ticket->loadMissing('faculty');
        $this->facultyCode = $ticket->faculty->code;
        $this->waiting = $ticket->status === TicketStatus::Menunggu
            ? 'menunggu di antrean selama '.Format::duration($ticket->created_at, now())
            : 'diproses, sudah berjalan '.Format::duration($ticket->created_at, now()).' sejak dibuat';
    }

    public function subject(): string
    {
        return "Ping dari Admin {$this->facultyCode}: {$this->ticket->ticket_number}";
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = $this->message($notifiable)
            ->line("{$this->adminName} (Admin {$this->facultyCode}) mengingatkan Anda tentang tiket berikut, yang {$this->waiting} (target SLA {$this->ticket->sla_target_hours} jam).");

        return $this->summary($mail)
            ->line(self::block(['Pesan Admin' => $this->adminMessage ?? 'Mohon tiket ini segera ditindaklanjuti.']))
            ->action('Buka Tiket', $this->ticketUrl($notifiable, $this->ticket));
    }
}
