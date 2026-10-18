<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabel 10: ratings — skor kepuasan (CSAT) 1-5, satu per tiket.
 */
#[Fillable(['ticket_id', 'user_id', 'score', 'comment'])]
class Rating extends Model
{
    /** Rating diberikan sekali dan tidak diubah, jadi hanya created_at. */
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** Pelapor yang memberi rating. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
