<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** F-NTF-03: email ke pelapor saat Staff mengirim balasan (bukan catatan internal). */
class StaffRepliedMail extends TicketMail
{
    public string $staffName;

    public string $excerpt;

    public function __construct(Comment $comment)
    {
        parent::__construct($comment->ticket);

        $this->staffName = $comment->user->name;
        $this->excerpt = Str::limit($comment->body, 300);
    }

    public function subject(): string
    {
        return "Balasan baru dari Staff: {$this->ticket->ticket_number}";
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = $this->message($notifiable)
            ->line("{$this->staffName} membalas tiket Anda:")
            ->line(new HtmlString('> '.e(str_replace(["\r\n", "\n"], ' ', $this->excerpt))));

        return $this->summary($mail)
            ->action('Buka Thread Tiket', $this->ticketUrl($notifiable, $this->ticket))
            ->line('Balas langsung di halaman tiket (bukan dengan membalas email ini).');
    }
}
