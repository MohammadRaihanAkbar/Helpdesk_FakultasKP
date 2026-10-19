<?php

namespace App\Http\Requests\Tickets;

use App\Services\Tickets\AttachmentStorage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi FORMAT form Buat Tiket (US-03, PRD 10.7).
 *
 * Aturan kategori yang lebih dalam (leaf? milik fakultas tujuan? aktif? audiens cocok?) dicek di
 * TicketCategoryTree::assertSelectable() karena butuh data katalog -> INVALID_CATEGORY.
 */
class StoreTicketRequest extends FormRequest
{
    public const TITLE_MAX = 150;

    public const DESCRIPTION_MAX = 2000;

    public function authorize(): bool
    {
        return true; // route dijaga middleware role:end_user
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->title) ? trim($this->title) : $this->title,
            'description' => is_string($this->description) ? trim($this->description) : $this->description,
        ]);
    }

    public function rules(): array
    {
        return [
            'faculty_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX],
            'description' => ['required', 'string', 'max:'.self::DESCRIPTION_MAX],
            // "Buat Ulang Tiket": id tiket lama + lampiran lama yang ikut dibawa
            'from' => ['nullable', 'integer'],
            'copy_attachments' => ['nullable', 'array'],
            'copy_attachments.*' => ['integer'],
            ...AttachmentStorage::rules(),
        ];
    }

    public function messages(): array
    {
        return [
            'faculty_id.required' => 'Pilih Fakultas Tujuan.',
            'category_id.required' => 'Pilih Kelompok Layanan (dan Jenis Masalah bila ada).',
            ...AttachmentStorage::messages(),
        ];
    }

    public function attributes(): array
    {
        return [
            'faculty_id' => 'fakultas tujuan',
        ];
    }
}
