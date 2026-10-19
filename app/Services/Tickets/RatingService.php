<?php

namespace App\Services\Tickets;

use App\Enums\TicketStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Rating;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Rating kepuasan / CSAT (US-05, F-TKU-07, tabel ratings).
 *
 * Aturan:
 *   - hanya untuk tiket berstatus Selesai (tiket Ditolak tidak dinilai);
 *   - satu kali per tiket -> 409 RATING_ALREADY_EXISTS. Dijaga dua lapis: dicek di sini, dan kolom
 *     ratings.ticket_id UNIQUE di database (kalau dua klik lolos bersamaan, yang kedua ditolak database).
 *
 * Siapa yang boleh (hanya pelapor pemilik tiket) dicek TicketPolicy::rate di controller.
 */
class RatingService
{
    public const COMMENT_MAX = 500; // PRD US-05: komentar opsional maks. 500 karakter

    public function rate(Ticket $ticket, User $reporter, int $score, ?string $comment): Rating
    {
        if ($ticket->status !== TicketStatus::Selesai) {
            throw BusinessRuleException::ratingNotAllowed();
        }

        if ($ticket->rating()->exists()) {
            throw BusinessRuleException::ratingAlreadyGiven();
        }

        try {
            return $ticket->rating()->create([
                'user_id' => $reporter->id,
                'score' => $score,
                'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw BusinessRuleException::ratingAlreadyGiven();
        }
    }
}
