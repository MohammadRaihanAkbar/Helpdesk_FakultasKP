<?php

namespace App\Services\Tickets;

use App\Enums\TicketStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Alur kerja Staff atas sebuah tiket (US-07, US-08, PRD 4.5 & 9.3):
 *
 *   assign()  Menunggu -> Diproses   isi handler_id + assigned_at        (PATCH /staff/tickets/{id}/assign)
 *   resolve() Diproses -> Selesai    WAJIB bukti, isi resolved_at        (PATCH /staff/tickets/{id}/status)
 *   reject()  Diproses -> Ditolak    WAJIB alasan, isi resolved_at       (PATCH /staff/tickets/{id}/status)
 *
 * SIAPA yang boleh (staff berspesialisasi / staff penangan) dicek lebih dulu oleh TicketPolicy di controller.
 * Service ini menjaga ATURAN BISNIS-nya: transisi status yang sah dan data wajib.
 *
 * [M5] Setiap perubahan status yang BERHASIL memberi tahu pelapor (email + lonceng) lewat TicketNotifier
 * (F-NTF-02, F-NTF-04, F-NTF-05). Klik ganda yang tidak mengubah apa pun tidak mengirim notifikasi.
 */
class TicketWorkflowService
{
    public function __construct(
        private readonly AttachmentStorage $storage,
        private readonly TicketNotifier $notifier,
    ) {}

    /**
     * Ambil tiket dari antrean.
     *
     * Aman dari balapan (dua staff klik "Ambil" di detik yang sama) karena memakai UPDATE BERSYARAT:
     *
     *   UPDATE tickets SET status='diproses', handler_id=?, assigned_at=?
     *   WHERE id=? AND status='menunggu' AND handler_id IS NULL
     *
     * Database menjalankan satu UPDATE secara utuh (atomik) dan mengunci barisnya selama itu. Staff pertama
     * mengubah 1 baris. Staff kedua menjalankan UPDATE yang sama, tetapi syarat WHERE sudah tidak terpenuhi,
     * jadi 0 baris berubah -> kita tahu ia kalah -> 409 TICKET_ALREADY_ASSIGNED.
     * Cara ini tidak perlu "baca dulu lalu tulis" (yang rawan disela request lain).
     */
    public function assign(Ticket $ticket, User $staff): Ticket
    {
        $won = Ticket::whereKey($ticket->id)
            ->where('status', TicketStatus::Menunggu->value)
            ->whereNull('handler_id')
            ->update([
                'status' => TicketStatus::Diproses->value,
                'handler_id' => $staff->id,
                'assigned_at' => now(),
            ]) === 1;

        $ticket->refresh();

        if ($won) {
            $this->notifier->statusChanged($ticket);

            return $ticket;
        }

        // Kalah balapan atau tiket memang sudah tidak menunggu.
        if ($ticket->handler_id === $staff->id && $ticket->status === TicketStatus::Diproses) {
            return $ticket; // klik ganda oleh staff yang sama: anggap berhasil, tidak perlu error
        }

        if ($ticket->handler_id !== null) {
            throw BusinessRuleException::ticketAlreadyAssigned();
        }

        throw BusinessRuleException::invalidStatusTransition($ticket->status, TicketStatus::Diproses);
    }

    /**
     * Ubah status lewat PATCH status. Hanya Selesai dan Ditolak yang bisa dituju di sini
     * (Diproses hanya lewat assign(); Menunggu tidak pernah bisa dituju lagi).
     *
     * @param  list<UploadedFile>  $proofs
     */
    public function changeStatus(Ticket $ticket, User $staff, TicketStatus $to, array $proofs = [], ?string $reason = null): Ticket
    {
        return match ($to) {
            TicketStatus::Selesai => $this->resolve($ticket, $staff, $proofs),
            TicketStatus::Ditolak => $this->reject($ticket, $staff, (string) $reason),
            default => throw BusinessRuleException::invalidStatusTransition($ticket->status, $to),
        };
    }

    /**
     * Tandai Selesai. Bukti penyelesaian WAJIB minimal 1 (maks. 3) dan disimpan di tabel resolution_proofs.
     *
     * @param  list<UploadedFile>  $proofs
     */
    public function resolve(Ticket $ticket, User $staff, array $proofs): Ticket
    {
        // Cek transisi dulu: kalau tiket masih Menunggu, pesan "ambil dulu" lebih berguna daripada "bukti wajib".
        $this->assertTransition($ticket, TicketStatus::Selesai);

        if ($proofs === []) {
            throw BusinessRuleException::proofRequired(); // 400, pesan persis PRD 9.3
        }

        if (count($proofs) > AttachmentStorage::MAX_FILES) {
            throw new BusinessRuleException('TOO_MANY_PROOFS', 'Bukti penyelesaian maksimal '.AttachmentStorage::MAX_FILES.' berkas.', 422, 'proofs');
        }

        // File disimpan SEBELUM transaksi; kalau transaksi gagal, file dihapus lagi (sama seperti lampiran tiket).
        $stored = [];

        try {
            foreach ($proofs as $file) {
                $stored[] = $this->storage->store($file, 'proofs');
            }

            DB::transaction(function () use ($ticket, $staff, $stored) {
                // Kunci baris tiket: dua klik "Selesai" bersamaan diproses bergantian, yang kedua melihat status Selesai.
                $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
                $this->assertTransition($locked, TicketStatus::Selesai);

                foreach ($stored as $file) {
                    $locked->resolutionProofs()->create($file + ['uploaded_by' => $staff->id]);
                }

                $locked->update(['status' => TicketStatus::Selesai, 'resolved_at' => now()]);
            });
        } catch (Throwable $e) {
            $this->storage->discard($stored);
            throw $e;
        }

        $ticket->refresh();
        $this->notifier->statusChanged($ticket);

        return $ticket;
    }

    /** Tolak tiket. Alasan WAJIB (termasuk alasan baku "Salah fakultas/kategori", lihat Ticket::REJECTION_REASONS). */
    public function reject(Ticket $ticket, User $staff, string $reason): Ticket
    {
        $this->assertTransition($ticket, TicketStatus::Ditolak);

        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleException::rejectionReasonRequired();
        }

        DB::transaction(function () use ($ticket, $reason) {
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($locked, TicketStatus::Ditolak);

            $locked->update([
                'status' => TicketStatus::Ditolak,
                'rejection_reason' => $reason,
                'resolved_at' => now(), // PRD v6.1: resolved_at = waktu tiket DITUTUP (Selesai maupun Ditolak)
            ]);
        });

        $ticket->refresh();
        $this->notifier->statusChanged($ticket);

        return $ticket;
    }

    private function assertTransition(Ticket $ticket, TicketStatus $to): void
    {
        if (! $ticket->status->canTransitionTo($to)) {
            throw BusinessRuleException::invalidStatusTransition($ticket->status, $to);
        }
    }
}
