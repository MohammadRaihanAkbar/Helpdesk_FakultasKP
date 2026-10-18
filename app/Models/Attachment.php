<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabel 8: attachments — lampiran pelapor/thread.
 * Diisi SALAH SATU: ticket_id (lampiran form awal) ATAU comment_id (lampiran pesan).
 */
#[Fillable(['ticket_id', 'comment_id', 'file_name', 'file_path', 'mime_type', 'file_size'])]
class Attachment extends Model
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

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }
}
