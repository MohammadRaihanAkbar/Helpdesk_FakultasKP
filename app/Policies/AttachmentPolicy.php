<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Unduh lampiran: hanya orang yang boleh melihat tiketnya (TicketPolicy::view).
 * Lampiran yang menempel di CATATAN INTERNAL hanya untuk Staff & Admin Fakultas, tidak untuk pelapor.
 */
class AttachmentPolicy
{
    public function download(User $user, Attachment $attachment): bool
    {
        $ticket = $attachment->ticket ?? $attachment->comment?->ticket;

        if ($ticket === null) {
            return false;
        }

        if ($attachment->comment?->is_internal_note) {
            return Gate::forUser($user)->allows('viewInternalNotes', $ticket);
        }

        return Gate::forUser($user)->allows('view', $ticket);
    }
}
