<?php

namespace App\Services\Tickets;

use App\Exceptions\BusinessRuleException;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Thread diskusi tiket (US-04, F-TKU-05, F-TKS-04, F-TKS-08).
 *
 * Aturan:
 *   - Maksimal 20 pesan PUBLIK per tiket (pelapor + staff). Pesan ke-21 -> THREAD_LIMIT_REACHED (409).
 *   - Catatan internal (is_internal_note) TIDAK dihitung dan tetap bisa dikirim walau thread publik penuh.
 *   - Tiket yang sudah ditutup (Selesai/Ditolak) tidak bisa dibalas -> THREAD_CLOSED (409).
 *   - Balasan publik PERTAMA dari staff penangan mengisi tickets.first_responded_at (dipakai di M3).
 *
 * Siapa yang boleh mengirim dicek lebih dulu oleh TicketPolicy::reply (controller).
 */
class CommentService
{
    public function __construct(private readonly AttachmentStorage $storage) {}

    /** Sisa kuota pesan publik, mis. untuk penghitung "12/20 pesan". */
    public function publicCount(Ticket $ticket): int
    {
        return $ticket->publicComments()->count();
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function post(Ticket $ticket, User $author, string $body, array $files = [], bool $internal = false): Comment
    {
        if (! $ticket->isOpen()) {
            throw BusinessRuleException::threadClosed();
        }

        if (count($files) > AttachmentStorage::MAX_FILES) {
            throw BusinessRuleException::tooManyAttachments(AttachmentStorage::MAX_FILES);
        }

        $stored = [];

        try {
            foreach ($files as $file) {
                $stored[] = $this->storage->store($file);
            }

            return DB::transaction(function () use ($ticket, $author, $body, $internal, $stored) {
                // Kunci baris tiket: dua orang yang mengirim pesan ke-20 bersamaan diproses bergantian,
                // jadi hitungan di bawah tidak bisa "kebobolan" menjadi 21.
                $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

                if (! $internal && $locked->publicComments()->count() >= Ticket::THREAD_LIMIT) {
                    throw BusinessRuleException::threadLimitReached(Ticket::THREAD_LIMIT);
                }

                $comment = $locked->comments()->create([
                    'user_id' => $author->id,
                    'body' => $body,
                    'is_internal_note' => $internal,
                ]);

                foreach ($stored as $file) {
                    $comment->attachments()->create($file); // comment_id terisi, ticket_id NULL
                }

                if (! $internal && $author->isStaff() && $locked->handler_id === $author->id && $locked->first_responded_at === null) {
                    $locked->update(['first_responded_at' => now()]);
                }

                return $comment;
            });
        } catch (Throwable $e) {
            $this->storage->discard($stored);
            throw $e;
        }
    }
}
