<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tabel 12: ping_logs — jejak teguran (Ping) Admin Fakultas ke Staff.
 */
#[Fillable(['ticket_id', 'admin_id', 'staff_id', 'message'])]
class PingLog extends Model
{
    const UPDATED_AT = null;

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** Admin Fakultas pengirim Ping. */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /** Staff penerima Ping. */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Satu aksi Ping ke 3 staff = 3 baris ping_logs. Untuk linimasa, baris dengan waktu, admin, dan pesan
     * yang sama digabung menjadi satu entri berisi daftar penerima. Terbaru di akhir (urutan waktu).
     *
     * @param  iterable<PingLog>  $logs  (sebaiknya sudah eager load admin & staff)
     * @return list<array{at: Carbon, admin: ?string, message: ?string, staff: list<string>}>
     */
    public static function groupForDisplay(iterable $logs): array
    {
        return collect($logs)
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->groupBy(fn (PingLog $log) => $log->created_at->format('YmdHis').'|'.$log->admin_id.'|'.md5((string) $log->message))
            ->map(fn ($group) => [
                'at' => $group->first()->created_at,
                'admin' => $group->first()->admin?->name,
                'message' => $group->first()->message,
                'staff' => $group->map(fn (PingLog $log) => $log->staff?->name)->filter()->values()->all(),
            ])
            ->values()
            ->all();
    }
}
