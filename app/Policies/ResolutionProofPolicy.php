<?php

namespace App\Policies;

use App\Models\ResolutionProof;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Unduh bukti penyelesaian (diunggah Staff saat menutup tiket, M3): siapa pun yang boleh melihat tiketnya,
 * termasuk pelapor (US-05: "Tiket Selesai menampilkan bukti penyelesaian").
 */
class ResolutionProofPolicy
{
    public function download(User $user, ResolutionProof $proof): bool
    {
        return Gate::forUser($user)->allows('view', $proof->ticket);
    }
}
