<?php

namespace App\Services\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * [M6] Pengingat rating: tiket Selesai yang belum dinilai pelapor mendapat email + notifikasi in-app.
 * Dijalankan perintah `php artisan helpdesk:pengingat-rating` (dijadwalkan tiap jam di routes/console.php).
 *
 * Aturan (angka di config/helpdesk.php -> rating_reminder, bisa diubah lewat .env):
 *
 *   status Selesai, BELUM ada baris ratings          (Ditolak tidak pernah: tidak bisa dirating)
 *   pengingat ke-1   >= first_after_hours (24) jam setelah resolved_at
 *   pengingat ke-n   >= interval_hours (48) jam setelah last_rating_reminder_at
 *   paling banyak    max_reminders (2) kali per tiket
 *   tiket lama       resolved_at lebih lama dari max_age_days (14) hari -> dilewati
 *   pelapor nonaktif -> dilewati
 *
 * IDEMPOTENT (aman dijalankan berkali-kali, bahkan bersamaan): sebelum mengirim, kolom rating_reminder_count dinaikkan
 * dengan UPDATE bersyarat "WHERE rating_reminder_count = nilai lama". Hanya proses yang UPDATE-nya berhasil
 * (1 baris berubah) yang mengirim pengingat, jadi satu pengingat tidak pernah terkirim dua kali.
 * Polanya sama dengan "Ambil Tiket" di TicketWorkflowService (M3).
 */
class RatingReminderService
{
    public function __construct(private readonly TicketNotifier $notifier) {}

    /**
     * Tiket yang SAAT INI perlu diingatkan.
     *
     * @param  bool  $ignoreDelay  true = abaikan jeda waktu (untuk demo `--paksa`); batas jumlah & umur tetap berlaku
     * @return Collection<int, Ticket>
     */
    public function due(?Carbon $now = null, bool $ignoreDelay = false): Collection
    {
        $now ??= now();
        $config = config('helpdesk.rating_reminder');

        return Ticket::query()
            ->where('status', TicketStatus::Selesai)
            ->whereNotNull('resolved_at')
            ->whereDoesntHave('rating')
            ->whereHas('reporter', fn (Builder $query) => $query->where('is_active', true))
            ->where('rating_reminder_count', '<', max(0, (int) $config['max_reminders']))
            ->where('resolved_at', '>=', $now->copy()->subDays((int) $config['max_age_days']))
            ->when(! $ignoreDelay, fn (Builder $query) => $query->where(fn (Builder $q) => $q
                // belum pernah diingatkan: hitung dari waktu selesai
                ->where(fn (Builder $first) => $first->where('rating_reminder_count', 0)
                    ->where('resolved_at', '<=', $now->copy()->subHours((int) $config['first_after_hours'])))
                // sudah pernah: hitung dari pengingat terakhir
                ->orWhere(fn (Builder $next) => $next->where('rating_reminder_count', '>', 0)
                    ->where('last_rating_reminder_at', '<=', $now->copy()->subHours((int) $config['interval_hours'])))))
            ->with(['reporter', 'category.parent', 'faculty'])
            ->orderBy('resolved_at')
            ->get();
    }

    /**
     * Kirim semua pengingat yang jatuh tempo.
     *
     * @return array{sent: int, skipped: int, failed: int, tickets: list<string>}
     */
    public function send(?Carbon $now = null, bool $ignoreDelay = false): array
    {
        $now ??= now();
        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'tickets' => []];

        foreach ($this->due($now, $ignoreDelay) as $ticket) {
            $count = $ticket->rating_reminder_count;

            // "Kunci" pengingat ini. 0 baris berubah = sudah diambil proses lain / sudah dirating di sela-sela.
            $claimed = Ticket::whereKey($ticket->id)
                ->where('rating_reminder_count', $count)
                ->whereDoesntHave('rating')
                ->update(['rating_reminder_count' => $count + 1, 'last_rating_reminder_at' => $now]);

            if ($claimed === 0) {
                $result['skipped']++;

                continue;
            }

            try {
                $this->notifier->ratingReminder($ticket, $count + 1);
                $result['sent']++;
                $result['tickets'][] = $ticket->ticket_number;
            } catch (Throwable $e) {
                // Kembalikan hitungan supaya dicoba lagi pada jadwal berikutnya.
                Ticket::whereKey($ticket->id)->update(['rating_reminder_count' => $count, 'last_rating_reminder_at' => $ticket->last_rating_reminder_at]);
                Log::error("Pengingat rating {$ticket->ticket_number} gagal: {$e->getMessage()}");
                $result['failed']++;
            }
        }

        return $result;
    }
}
