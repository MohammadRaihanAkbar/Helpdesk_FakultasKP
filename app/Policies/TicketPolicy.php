<?php

namespace App\Policies;

use App\Models\StaffSpecialty;
use App\Models\Ticket;
use App\Models\User;

/**
 * Siapa boleh melihat / membalas sebuah tiket.
 *
 *   Pelapor         -> hanya tiket miliknya sendiri (PRD 2.1 "Lihat Tiket Orang Lain ❌")
 *   Staff           -> tiket yang ia tangani, atau tiket fakultasnya di Kelompok Layanan spesialisasinya
 *                      (sama dengan antrean, PRD 4.6) — halaman staff dibuat di M3
 *   Admin Fakultas  -> semua tiket fakultasnya (monitoring), tetapi TIDAK ikut berdiskusi
 *   Super Admin     -> tidak menangani tiket sama sekali
 *
 * Controller memakai: Gate::authorize('view', $ticket) -> gagal = 403.
 */
class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return match (true) {
            $user->isEndUser() => $ticket->reporter_id === $user->id,
            $user->isStaff() => $this->isHandler($user, $ticket) || $this->inStaffSpecialty($user, $ticket),
            $user->isAdmin() => $user->faculty_id !== null && $user->faculty_id === $ticket->faculty_id,
            default => false,
        };
    }

    /**
     * Boleh mengirim pesan publik di thread: pelapor pemilik tiket dan Staff penangan (M3).
     * Status tiket (harus masih terbuka) & batas 20 pesan dicek di CommentService.
     */
    public function reply(User $user, Ticket $ticket): bool
    {
        return ($user->isEndUser() && $ticket->reporter_id === $user->id)
            || $this->isHandler($user, $ticket);
    }

    /** Catatan internal hanya terlihat oleh Staff & Admin Fakultas yang boleh melihat tiket (F-TKS-08). */
    public function viewInternalNotes(User $user, Ticket $ticket): bool
    {
        return ($user->isStaff() || $user->isAdmin()) && $this->view($user, $ticket);
    }

    /**
     * Identitas pelapor (nama, NIM/NIDN, email).
     *
     * Kategori sensitif "Integritas Dosen" (PRD 2.7 aturan 10): HANYA Staff penangan dan Admin Fakultas
     * (plus pelapor itu sendiri). Staff lain yang kebetulan punya spesialisasi itu melihat tiketnya di antrean,
     * tetapi nama pelapornya disamarkan sampai tiket diambil.
     * Kategori biasa: siapa pun yang boleh melihat tiket boleh melihat pelapornya.
     */
    public function viewReporterIdentity(User $user, Ticket $ticket): bool
    {
        if (! $this->view($user, $ticket)) {
            return false;
        }

        if ($ticket->reporter_id === $user->id || ! $ticket->category->isSensitive()) {
            return true;
        }

        return $user->isAdmin() || $this->isHandler($user, $ticket);
    }

    /**
     * "Buat Ulang Tiket" (US-05): hanya pemilik tiket, dan hanya tiket Ditolak dengan alasan baku
     * "Salah fakultas/kategori".
     */
    public function recreate(User $user, Ticket $ticket): bool
    {
        return $user->isEndUser()
            && $ticket->reporter_id === $user->id
            && $ticket->isRejectedForWrongTarget();
    }

    private function isHandler(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $ticket->handler_id !== null && $ticket->handler_id === $user->id;
    }

    private function inStaffSpecialty(User $staff, Ticket $ticket): bool
    {
        return $staff->faculty_id !== null
            && $staff->faculty_id === $ticket->faculty_id
            && StaffSpecialty::where('staff_id', $staff->id)->where('category_id', $ticket->group()->id)->exists();
    }
}
