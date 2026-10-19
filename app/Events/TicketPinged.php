<?php

namespace App\Events;

use App\Models\PingLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/**
 * Peristiwa "Admin Fakultas mengirim Ping" (US-14, F-NTF-06).
 *
 * Dikirim SETELAH ping_logs & notifikasi in-app tersimpan (lihat PingService). Saat ini belum ada
 * pendengar (listener). Di M5, email teguran cukup ditambahkan sebagai listener baru, misalnya:
 *
 *   php artisan make:listener SendPingEmail --event=TicketPinged
 *
 * Laravel menemukan listener di app/Listeners secara otomatis, jadi PingService tidak perlu diubah.
 */
class TicketPinged
{
    use Dispatchable;

    /**
     * @param  Collection<int, User>  $staff  penerima Ping
     * @param  Collection<int, PingLog>  $logs  satu baris per penerima
     */
    public function __construct(
        public readonly Ticket $ticket,
        public readonly User $admin,
        public readonly Collection $staff,
        public readonly Collection $logs,
        public readonly ?string $message,
    ) {}
}
