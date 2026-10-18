<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabel 9: resolution_proofs — bukti wajib dari Staff sebelum tiket "selesai".
 */
#[Fillable(['ticket_id', 'file_name', 'file_path', 'mime_type', 'file_size', 'uploaded_by'])]
class ResolutionProof extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** Staff yang mengunggah bukti (kolom uploaded_by, bukan user_id). */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
