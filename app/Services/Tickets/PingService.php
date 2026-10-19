<?php

namespace App\Services\Tickets;

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Events\TicketPinged;
use App\Exceptions\BusinessRuleException;
use App\Models\Notification;
use App\Models\PingLog;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ping / Ingatkan Staff (US-14, F-ADM-06, PRD 9.3 POST /tickets/:id/ping).
 *
 * Siapa yang menerima Ping?
 *   - Tiket MENUNGGU  -> SEMUA Staff aktif di fakultas tiket yang spesialisasinya mencakup Kelompok Layanan
 *                        tiket (= staff yang melihat tiket itu di antreannya, PRD 4.6 & US-14).
 *                        Tidak ada staff seperti itu -> 422 "Belum ada Staff untuk kelompok ini — ...".
 *   - Tiket DIPROSES  -> Staff penangan (handler) saja, karena hanya dia yang bisa menindaklanjuti
 *                        (PRD 2.3: "Staff yang memiliki tiket menganggur", mockup 10.5).
 *   - Tiket ditutup   -> 409, tidak ada yang perlu diingatkan.
 *
 * Setiap Ping menyimpan SATU baris ping_logs per Staff penerima + satu notifikasi in-app per Staff
 * (tabel notifications, type = in_app). Email teguran dibuat di M5 lewat event TicketPinged.
 *
 * Batas spam: PRD US-14 menyatakan "Tidak ada batasan jumlah Ping". Jadi yang dibatasi hanya klik ganda:
 * Ping ke tiket yang SAMA dalam COOLDOWN_SECONDS detik ditolak (429). Ditambah pembatas route
 * 'pings' (20 Ping/menit per Admin) untuk menahan skrip. Admin tetap bisa Ping berulang kali.
 */
class PingService
{
    public const COOLDOWN_SECONDS = 30;

    public const MESSAGE_MAX = 500;

    /**
     * @return array{logs: Collection<int, PingLog>, staff: Collection<int, User>, pinged_at: Carbon}
     */
    public function ping(Ticket $ticket, User $admin, ?string $message): array
    {
        if (! $ticket->isOpen()) {
            throw BusinessRuleException::pingClosedTicket();
        }

        $recipients = $this->recipients($ticket);
        if ($recipients->isEmpty()) {
            throw $ticket->status === TicketStatus::Diproses
                ? BusinessRuleException::handlerInactive()
                : BusinessRuleException::noStaffForGroup();
        }

        $last = $ticket->pingLogs()->max('created_at');
        if ($last !== null && now()->diffInSeconds($last, true) < self::COOLDOWN_SECONDS) {
            throw BusinessRuleException::pingTooSoon(self::COOLDOWN_SECONDS);
        }

        $message = trim((string) $message) === '' ? null : trim((string) $message);
        $now = now();

        $logs = DB::transaction(function () use ($ticket, $admin, $message, $recipients, $now) {
            $ticket->loadMissing('category.parent', 'faculty');
            $logs = collect();

            foreach ($recipients as $staff) {
                $logs->push(PingLog::create([
                    'ticket_id' => $ticket->id,
                    'admin_id' => $admin->id,
                    'staff_id' => $staff->id,
                    'message' => $message,
                ])->setAttribute('created_at', $now));

                Notification::create([
                    'user_id' => $staff->id,
                    'ticket_id' => $ticket->id,
                    'type' => NotificationType::InApp,
                    'subject' => Str::limit("Ping dari Admin {$ticket->faculty->code}: {$ticket->ticket_number}", 197),
                    'message' => $this->notificationText($ticket, $message, $now),
                    'is_read' => false,
                    'sent_at' => $now,
                ]);
            }

            return $logs;
        });

        TicketPinged::dispatch($ticket, $admin, $recipients, $logs, $message);

        return ['logs' => $logs, 'staff' => $recipients, 'pinged_at' => $now];
    }

    /**
     * Staff yang akan menerima Ping (dipakai juga untuk menonaktifkan tombol Ping di tampilan).
     *
     * @return Collection<int, User>
     */
    public function recipients(Ticket $ticket): Collection
    {
        if ($ticket->status === TicketStatus::Diproses) {
            $handler = $ticket->handler;

            return $handler && $handler->is_active ? collect([$handler]) : collect();
        }

        if ($ticket->status !== TicketStatus::Menunggu) {
            return collect();
        }

        return User::query()
            ->where('role', Role::Staff)
            ->where('is_active', true)
            ->where('faculty_id', $ticket->faculty_id)
            ->whereHas('staffSpecialties', fn ($query) => $query->where('category_id', $ticket->group()->id))
            ->orderBy('name')
            ->get();
    }

    /**
     * Jumlah calon penerima Ping untuk banyak tiket sekaligus (daftar Monitor Antrean & dashboard),
     * tanpa satu query per baris. Tiket ditutup = 0.
     *
     * @param  iterable<Ticket>  $tickets  (butuh relasi category & handler)
     * @return array<int, int> [ticket_id => jumlah staff]
     */
    public function recipientCounts(iterable $tickets, int $facultyId): array
    {
        $perGroup = DB::table('staff_specialties')
            ->join('users', 'users.id', '=', 'staff_specialties.staff_id')
            ->where('users.role', Role::Staff->value)
            ->where('users.is_active', true)
            ->where('users.faculty_id', $facultyId)
            ->groupBy('staff_specialties.category_id')
            ->selectRaw('staff_specialties.category_id as group_id, COUNT(*) as total')
            ->pluck('total', 'group_id');

        $counts = [];
        foreach ($tickets as $ticket) {
            $counts[$ticket->id] = match ($ticket->status) {
                TicketStatus::Menunggu => (int) ($perGroup[$ticket->group()->id] ?? 0),
                TicketStatus::Diproses => $ticket->handler?->is_active ? 1 : 0,
                default => 0,
            };
        }

        return $counts;
    }

    /**
     * Isi notifikasi (US-14): nomor tiket, judul, kategori, lama menunggu, dan pesan teguran Admin.
     * Identitas pelapor sengaja TIDAK disertakan (aman untuk kategori sensitif "Integritas Dosen").
     */
    private function notificationText(Ticket $ticket, ?string $message, \DateTimeInterface $now): string
    {
        $waiting = $ticket->status === TicketStatus::Menunggu
            ? 'Menunggu di antrean selama '.Format::duration($ticket->created_at, $now)
            : 'Diproses, sudah berjalan '.Format::duration($ticket->created_at, $now).' sejak dibuat';

        return implode("\n", [
            "{$ticket->ticket_number} — {$ticket->title}",
            'Kategori: '.$ticket->category->pathLabel(),
            "{$waiting} (target SLA {$ticket->sla_target_hours} jam).",
            'Pesan Admin: '.($message ?? 'Mohon tiket ini segera ditindaklanjuti.'),
        ]);
    }
}
