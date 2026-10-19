<?php

namespace App\Http\Requests\Tickets;

use App\Services\Tickets\RatingService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /tickets/{ticket}/rating — rating bintang 1–5 + komentar opsional maks. 500 karakter (US-05).
 * "Hanya tiket Selesai" dan "sekali per tiket" dicek di RatingService.
 */
class StoreRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin dicek controller lewat TicketPolicy::rate
    }

    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:'.RatingService::COMMENT_MAX],
        ];
    }

    public function messages(): array
    {
        return [
            'score.required' => 'Pilih jumlah bintang (1–5).',
            'score.integer' => 'Pilih jumlah bintang (1–5).',
            'score.between' => 'Rating harus antara 1 sampai 5 bintang.',
            'comment.max' => 'Komentar maksimal '.RatingService::COMMENT_MAX.' karakter.',
        ];
    }
}
