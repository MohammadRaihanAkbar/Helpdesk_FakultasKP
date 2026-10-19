<?php

namespace App\Http\Requests\Tickets;

use App\Services\Tickets\AttachmentStorage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Balasan di thread tiket (US-04): teks pesan + lampiran opsional (JPG/PNG/PDF, maks 5MB, maks 3).
 * Batas 20 pesan dicek di CommentService (butuh menghitung pesan yang sudah ada).
 */
class StoreCommentRequest extends FormRequest
{
    /** Panjang maksimal satu pesan. Keputusan teknis: disamakan dengan deskripsi tiket (2000). */
    public const BODY_MAX = 2000;

    public function authorize(): bool
    {
        return true; // izin dicek controller lewat TicketPolicy::reply
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['body' => is_string($this->body) ? trim($this->body) : $this->body]);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.self::BODY_MAX],
            ...AttachmentStorage::rules(),
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Tulis pesan terlebih dahulu.',
            ...AttachmentStorage::messages(),
        ];
    }
}
