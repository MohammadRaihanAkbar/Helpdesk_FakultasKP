<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabel 11: notifications — riwayat notifikasi versi PRD (BUKAN tabel bawaan Laravel).
 *
 * Tidak memakai created_at/updated_at; waktunya ada di sent_at (default CURRENT_TIMESTAMP
 * dari database) dan read_at. Karena itu timestamps bawaan dimatikan.
 *
 * Hati-hati nama: Illuminate\Notifications\Notification adalah class lain (pengirim notifikasi).
 * Yang ini App\Models\Notification = baris catatan di database.
 */
#[Fillable(['user_id', 'ticket_id', 'type', 'subject', 'message', 'is_read', 'sent_at', 'read_at'])]
#[WithoutTimestamps]
class Notification extends Model
{
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'is_read' => 'boolean',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /** Penerima notifikasi. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
