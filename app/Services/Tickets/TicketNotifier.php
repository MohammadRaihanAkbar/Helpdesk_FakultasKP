<?php

namespace App\Services\Tickets;

use App\Enums\NotificationType;
use App\Enums\TicketStatus;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Satu tempat untuk semua notifikasi alur tiket (M5, PRD bab 5 modul NOTIF). Saat ini HANYA in-app (lonceng):
 * baris tabel notifications type = in_app, tampil di badge lonceng, halaman /notifications, dan dashboard pelapor.
 *
 *   Peristiwa                        Penerima                    Subjek notifikasi                      PRD
 *   tiket dibuat                     pelapor                     "Tiket TKT-... berhasil dibuat"        F-NTF-01
 *   diambil Staff (Diproses)         pelapor                     "Tiket TKT-... sedang diproses"        F-NTF-02
 *   ditolak                          pelapor                     "Tiket TKT-... ditolak"                F-NTF-02
 *   selesai (+ ajakan rating)        pelapor                     "Tiket TKT-... selesai"                F-NTF-04
 *   Staff membalas (bukan internal)  pelapor                     "Balasan baru dari Staff: TKT-..."     F-NTF-03, US-02
 *   pesan publik ke-20               pelapor + Staff penangan    "Thread TKT-... mencapai batas 20 pesan" F-NTF-07
 *   (Ping Admin -> Staff tetap ditulis PingService sejak M4, F-NTF-06)
 *
 * EMAIL SENGAJA DITUNDA (permintaan M5: "untuk sekarang gausah send ke email dulu"). Cara menambahkannya nanti:
 * buat class Notification Laravel (php artisan make:notification, implements ShouldQueue, $tries = 3) lalu panggil
 * $user->notify(...) di method notify() di bawah. Semua peristiwa sudah lewat method itu, jadi service lain
 * (TicketService, TicketWorkflowService, CommentService) tidak perlu diubah lagi.
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
                "\"{$ticket->title}\" diterima {$ticket->faculty->code}. Target penyelesaian {$ticket->sla_target_hours} jam. Simpan nomor tiket ini.");
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

            $this->notify($ticket->reporter, $ticket, $subject, $message);
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
                $this->notify($ticket->reporter, $ticket, self::REPLY_SUBJECT.": {$ticket->ticket_number}", Str::limit($comment->body, 300));
            }

            // Tepat saat pesan publik ke-20 tersimpan (sekali saja): beri tahu pelapor & Staff penangan.
            if ($ticket->publicComments()->count() === Ticket::THREAD_LIMIT) {
                collect([$ticket->reporter, $ticket->handler])
                    ->filter(fn (?User $user) => $user !== null && $user->is_active)
                    ->each(fn (User $user) => $this->notify($user, $ticket,
                        "Thread {$ticket->ticket_number} mencapai batas ".Ticket::THREAD_LIMIT.' pesan',
                        'Pesan baru tidak bisa dikirim lagi. Staff penangan dapat langsung menyelesaikan atau menutup tiket ini.'));
            }
        });
    }

    /**
     * Kirim satu notifikasi ke satu user. Sekarang: baris in-app saja.
     * (Titik untuk menambah email nanti: $user->notify(new ...) di sini.)
     */
    private function notify(User $user, Ticket $ticket, string $subject, string $message): void
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
    }
}
