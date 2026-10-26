<?php

namespace App\Services\Tickets;

use App\Enums\TicketStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Membuat tiket baru oleh pelapor (US-03, US-05B, F-TKU-01..03, F-SYS-03).
 *
 * Urutan kerja create():
 *   1. Validasi kategori (leaf, fakultas tujuan, aktif, audiens)           -> INVALID_CATEGORY
 *   2. Hitung lampiran (baru + salinan dari tiket lama) maks. 3            -> TOO_MANY_ATTACHMENTS
 *   3. Simpan file ke disk privat (di luar transaksi)
 *   4. Transaksi: nomor tiket + insert tiket + insert baris lampiran
 *      - snapshot sla_target_hours (dari kategori) & reporter_type (dari user) -> tidak ikut berubah nanti
 *      - status awal "menunggu", tanpa handler, tanpa batas kedaluwarsa
 *   5. Nomor bentrok (sangat jarang) -> ulangi transaksi, maksimal 5 kali
 *   6. [M5] Email "tiket berhasil dibuat" berisi nomor tiket (F-NTF-01), lewat antrean, SETELAH transaksi
 */
class TicketService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly TicketNumberGenerator $numbers,
        private readonly TicketCategoryTree $categories,
        private readonly AttachmentStorage $storage,
        private readonly TicketNotifier $notifier,
    ) {}

    /**
     * @param  array{faculty_id: mixed, category_id: mixed, title: string, description: string}  $data
     * @param  list<UploadedFile>  $files  lampiran baru
     * @param  Ticket|null  $from  tiket Ditolak "Salah fakultas/kategori" yang dibuat ulang (US-05)
     * @param  list<int>  $copyAttachmentIds  id lampiran tiket lama yang ikut dibawa
     */
    public function create(User $reporter, array $data, array $files = [], ?Ticket $from = null, array $copyAttachmentIds = []): Ticket
    {
        $category = $this->categories->assertSelectable($data['category_id'], $data['faculty_id'], $reporter->user_type);

        $toCopy = $from
            ? $from->attachments()->whereIn('id', $copyAttachmentIds)->get()
            : collect();

        if (count($files) + $toCopy->count() > AttachmentStorage::MAX_FILES) {
            throw BusinessRuleException::tooManyAttachments(AttachmentStorage::MAX_FILES);
        }

        $stored = [];

        try {
            foreach ($files as $file) {
                $stored[] = $this->storage->store($file);
            }
            foreach ($toCopy as $old) {
                if ($copy = $this->storage->copy($old)) {
                    $stored[] = $copy;
                }
            }

            for ($attempt = 1; ; $attempt++) {
                try {
                    $ticket = DB::transaction(function () use ($reporter, $data, $category, $stored) {
                        $ticket = Ticket::create([
                            'ticket_number' => $this->numbers->next(),
                            'faculty_id' => $category->faculty_id,
                            'title' => $data['title'],
                            'description' => $data['description'],
                            'status' => TicketStatus::Menunggu,
                            'reporter_id' => $reporter->id,
                            'reporter_type' => $reporter->user_type,     // snapshot jenis pelapor
                            'category_id' => $category->id,               // selalu kategori LEAF
                            'sla_target_hours' => $category->sla_hours,  // snapshot SLA saat dibuat
                        ]);

                        foreach ($stored as $file) {
                            $ticket->attachments()->create($file); // ticket_id terisi, comment_id NULL
                        }

                        return $ticket;
                    });

                    break;
                } catch (UniqueConstraintViolationException $e) {
                    if ($attempt >= self::MAX_ATTEMPTS) {
                        throw $e;
                    }
                }
            }
        } catch (Throwable $e) {
            $this->storage->discard($stored); // jangan tinggalkan file yatim
            throw $e;
        }

        // Di luar try: tiket sudah tersimpan, jadi masalah notifikasi tidak boleh menghapus lampirannya.
        $this->notifier->created($ticket);

        return $ticket;
    }
}
