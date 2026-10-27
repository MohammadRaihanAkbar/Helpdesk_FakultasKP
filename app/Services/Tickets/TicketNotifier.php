<?php

namespace App\Services\Tickets;

use App\Enums\NotificationType;
use App\Enums\TicketStatus;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\HelpdeskMail;
use App\Notifications\RatingReminderMail;
use App\Notifications\StaffRepliedMail;
use App\Notifications\ThreadLimitMail;
use App\Notifications\TicketCreatedMail;
use App\Notifications\TicketResolvedMail;
use App\Notifications\TicketStatusChangedMail;
use Illuminate\Support\Str;

/**
 * Satu tempat untuk semua notifikasi alur tiket (M5, PRD bab 5 modul NOTIF):
 *   - in-app (lonceng): baris tabel notifications type = in_app, tampil di badge lonceng, /notifications, dashboard pelapor;
 *   - [M6] EMAIL: class di app/Notifications, dikirim lewat antrean ke users.email (SMTP Gmail, lihat HelpdeskMail).
 *
 *   Peristiwa                        Penerima                    Subjek notifikasi                      PRD
 *   tiket dibuat                     pelapor                     "Tiket TKT-... berhasil dibuat"        F-NTF-01
 *   diambil Staff (Diproses)         pelapor                     "Tiket TKT-... sedang diproses"        F-NTF-02
 *   ditolak                          pelapor                     "Tiket TKT-... ditolak"                F-NTF-02
 *   selesai (+ ajakan rating)        pelapor                     "Tiket TKT-... selesai"                F-NTF-04
 *   Staff membalas (bukan internal)  pelapor                     "Balasan baru dari Staff: TKT-..."     F-NTF-03, US-02
 *   pesan publik ke-20               pelapor + Staff penangan    "Thread TKT-... mencapai batas 20 pesan" F-NTF-07
 *   [M6] pengingat rating            pelapor                     "Pengingat: beri penilaian tiket TKT-..."  (RatingReminderService)
 *   (Ping Admin -> Staff tetap ditulis PingService sejak M4, F-NTF-06; emailnya lewat listener SendPingEmails)
 *
 * [M6] Email: setiap peristiwa di atas juga mengirim email (class *Mail di app/Notifications). Persis seperti
 * rencana M5, cukup method notify() di bawah yang berubah; TicketService, TicketWorkflowService, dan CommentService
 * tidak disentuh. Penerima tanpa email asli (email pengganti SSO) hanya mendapat notifikasi in-app.
 *
 * Dipanggil SETELAH transaksi masing-masing service selesai. Dibungkus rescue(): kalau penulisan notifikasi gagal,
 * errornya dicatat di log, tetapi aksi utamanya (tiket tersimpan, status berubah) TIDAK ikut gagal.
 */
class TicketNotifier
{
    /** Awalan subjek notifikasi balasan Staff (dipakai dashboard pelapor untuk penanda "Balasan baru"). */
    public const REPLY_SUBJECT = 'Balasan baru dari Staff';

    public function created(Ticket $ticket): void
    {
        rescue(function () use ($ticket) {
            $ticket->loadMissing('reporter', 'faculty');

            $this->notify($ticket->reporter, $ticket, "Tiket {$ticket->ticket_number} berhasil dibuat",
                "\"{$ticket->title}\" diterima {$ticket->faculty->code}. Target penyelesaian {$ticket->sla_target_hours} jam. Simpan nomor tiket ini.",
                new TicketCreatedMail($ticket));
        });
    }

    /** Status berubah menjadi Diproses, Selesai, atau Ditolak. */
    public function statusChanged(Ticket $ticket): void
    {
        rescue(function () use ($ticket) {
            $ticket->loadMissing('reporter', 'handler');

            [$subject, $message] = match ($ticket->status) {
                TicketStatus::Diproses => ["Tiket {$ticket->ticket_number} sedang diproses", 'Diambil oleh '.($ticket->handler?->name ?? 'Staff').'.'],
                // Klik notifikasi ini membuka form rating (NotificationController::ticketUrl -> #rating).
                TicketStatus::Selesai => ["Tiket {$ticket->ticket_number} selesai", 'Lihat bukti penyelesaian dan beri penilaian 1–5 bintang.'],
                TicketStatus::Ditolak => ["Tiket {$ticket->ticket_number} ditolak", 'Alasan: '.$ticket->rejection_reason],
                default => ["Status tiket {$ticket->ticket_number}: {$ticket->status->label()}", $ticket->title],
            };

            // Selesai -> email khusus dengan link rating (F-NTF-04); Diproses/Ditolak -> email perubahan status (F-NTF-02).
            $mail = $ticket->status === TicketStatus::Selesai ? new TicketResolvedMail($ticket) : new TicketStatusChangedMail($ticket);

            $this->notify($ticket->reporter, $ticket, $subject, $message, $mail);
        });
    }

    /** Pesan baru di thread. Catatan internal tidak pernah memicu notifikasi ke pelapor. */
    public function commentPosted(Ticket $ticket, Comment $comment): void
    {
        if ($comment->is_internal_note) {
            return;
        }

        rescue(function () use ($ticket, $comment) {
            $ticket->loadMissing('reporter', 'handler');
            $author = $comment->user;

            if ($author->isStaff() && $author->id !== $ticket->reporter_id) {
                $this->notify($ticket->reporter, $ticket, self::REPLY_SUBJECT.": {$ticket->ticket_number}", Str::limit($comment->body, 300),
                    new StaffRepliedMail($comment));
            }

            // Tepat saat pesan publik ke-20 tersimpan (sekali saja): beri tahu pelapor & Staff penangan.
            if ($ticket->publicComments()->count() === Ticket::THREAD_LIMIT) {
                collect([$ticket->reporter, $ticket->handler])
                    ->filter(fn (?User $user) => $user !== null && $user->is_active)
                    ->each(fn (User $user) => $this->notify($user, $ticket,
                        "Thread {$ticket->ticket_number} mencapai batas ".Ticket::THREAD_LIMIT.' pesan',
                        'Pesan baru tidak bisa dikirim lagi. Staff penangan dapat langsung menyelesaikan atau menutup tiket ini.',
                        new ThreadLimitMail($ticket)));
            }
        });
    }

    /**
     * [M6] Pengingat rating untuk tiket Selesai yang belum dinilai (dipanggil RatingReminderService).
     * Tidak dibungkus rescue() di sini: perintah artisan-nya yang mencatat kegagalan per tiket.
     */
    public function ratingReminder(Ticket $ticket, int $number): void
    {
        $ticket->loadMissing('reporter');

        $this->notify($ticket->reporter, $ticket, "Pengingat: beri penilaian tiket {$ticket->ticket_number}",
            "Tiket \"{$ticket->title}\" sudah selesai tetapi belum dinilai. Klik untuk memberi penilaian 1–5 bintang.",
            new RatingReminderMail($ticket, $number));
    }

    /**
     * Kirim satu notifikasi ke satu user: baris in-app, lalu (M6) email bila ada.
     *
     * Email dikirim lewat $user->notify(): masuk antrean (ShouldQueue), jadi di sini hanya "dicatat untuk dikirim".
     * Dibungkus rescue() sendiri: kalau antrean/SMTP bermasalah, notifikasi in-app untuk penerima lain tetap jalan
     * dan aksi utamanya tidak gagal. Errornya tercatat di storage/logs/laravel.log.
     */
    private function notify(User $user, Ticket $ticket, string $subject, string $message, ?HelpdeskMail $mail = null): void
    {
        Notification::create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'type' => NotificationType::InApp,
            'subject' => Str::limit($subject, 197),
            'message' => $message,
            'is_read' => false,
            'sent_at' => now(),
        ]);

        if ($mail !== null && $user->canReceiveEmail()) {
            rescue(fn () => $user->notify($mail));
        }
    }
}
