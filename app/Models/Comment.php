<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabel 7: comments — pesan thread tiket (publik atau internal note).
 * Batas 20 pesan publik dijaga di aplikasi (M2), bukan di sini.
 */
#[Fillable(['ticket_id', 'user_id', 'body', 'is_internal_note'])]
class Comment extends Model
{
    /** Pesan tidak bisa diedit, jadi tabelnya hanya punya created_at. */
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'is_internal_note' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** Pengirim pesan. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
