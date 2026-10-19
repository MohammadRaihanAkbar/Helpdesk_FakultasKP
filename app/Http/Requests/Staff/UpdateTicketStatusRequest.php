<?php

namespace App\Http\Requests\Staff;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Tickets\AttachmentStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * PATCH /staff/tickets/{ticket}/status — Staff menutup tiket (US-08, PRD 9.3).
 *
 * Isian:
 *   status       'selesai' | 'ditolak'
 *   proofs[]     bukti penyelesaian (untuk Selesai): JPG/PNG/PDF, maks. 5 MB, maks. 3 berkas
 *   reason       kunci alasan baku (untuk Ditolak), lihat Ticket::REJECTION_REASONS
 *   reason_note  keterangan tambahan (opsional; WAJIB bila reason = 'lainnya')
 *   rejection_reason  (alternatif untuk Postman/API) teks alasan langsung, seperti nama kolom di PRD
 *
 * Yang dicek DI SINI hanya format. "Bukti wajib" (400) dan "alasan wajib" (400) sengaja dicek di
 * TicketWorkflowService supaya kode & pesannya persis PRD 9.3, dan transisi status (409) juga di sana.
 */
class UpdateTicketStatusRequest extends FormRequest
{
    public const NOTE_MAX = 1000;

    public function authorize(): bool
    {
        return true; // izin dicek controller lewat TicketPolicy::handle
    }

    protected function prepareForValidation(): void
    {
        foreach (['reason_note', 'rejection_reason'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TicketStatus::class)],
            ...AttachmentStorage::rules('proofs'),
            'reason' => ['nullable', 'string', Rule::in(array_keys(Ticket::REJECTION_REASONS))],
            'reason_note' => ['nullable', 'string', 'max:'.self::NOTE_MAX, 'required_if:reason,lainnya'],
            'rejection_reason' => ['nullable', 'string', 'max:'.self::NOTE_MAX],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Pilih status tujuan.',
            'status.enum' => 'Status tidak dikenal.',
            'reason.in' => 'Pilih alasan penolakan dari daftar.',
            'reason_note.required_if' => 'Tuliskan alasan penolakan bila memilih "Alasan lainnya".',
            ...AttachmentStorage::messages('proofs', 'Bukti'),
        ];
    }

    public function targetStatus(): TicketStatus
    {
        return TicketStatus::from($this->validated('status'));
    }

    /**
     * Teks yang disimpan di tickets.rejection_reason:
     *   pilihan baku               -> "Salah fakultas/kategori"
     *   pilihan baku + keterangan  -> "Salah fakultas/kategori: seharusnya ke FEBI"
     *   'lainnya'                  -> keterangannya saja
     *   tanpa pilihan              -> isi rejection_reason (Postman) atau string kosong (-> 400 di service)
     *
     * Awalan teks baku dipertahankan apa adanya karena tombol "Buat Ulang Tiket" di sisi pelapor
     * mengenali penolakan "Salah fakultas/kategori" dari AWALAN teks ini (Ticket::isRejectedForWrongTarget()).
     */
    public function rejectionReason(): string
    {
        $key = $this->validated('reason');
        $note = (string) $this->validated('reason_note');

        if ($key === null) {
            return (string) $this->validated('rejection_reason');
        }

        if ($key === 'lainnya') {
            return $note;
        }

        $preset = Ticket::REJECTION_REASONS[$key];

        return $note === '' ? $preset : "{$preset}: {$note}";
    }

    /** @return list<UploadedFile> */
    public function proofFiles(): array
    {
        return array_values($this->file('proofs', []));
    }
}
