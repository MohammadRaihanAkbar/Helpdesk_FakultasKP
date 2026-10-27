<?php

namespace App\Services\Reports;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * [M6] Laporan hasil kerja SATU Staff (/staff/laporan): "berapa tiket yang saya kerjakan dalam N hari terakhir?"
 *
 * Periode: preset 7 / 14 / 30 / 90 hari terakhir (bawaan 30) atau rentang tanggal sendiri (from–to, maks. 1 tahun).
 *
 * Tiket yang masuk laporan = tiket yang ditangani Staff ini (handler_id) DAN punya kegiatan di periode itu:
 *   diambil (assigned_at) di periode  ATAU  ditutup (resolved_at) di periode.
 * Jadi tiket yang diambil minggu lalu dan baru selesai hari ini ikut dihitung di laporan 7 hari.
 *
 * Angka (sama rumusnya dengan TicketMetrics, PRD 1.4):
 *   Diambil           tiket yang assigned_at-nya di periode
 *   Selesai / Ditolak tiket yang DITUTUP (resolved_at) di periode dengan status itu
 *   Masih diproses    tiket di laporan yang statusnya masih Diproses sekarang
 *   Rata-rata penyelesaian  dibuat -> selesai, dari tiket Selesai di periode
 *   Rata-rata respons pertama  dibuat -> balasan pertama Staff (first_responded_at, FRT PRD), dari tiket di laporan
 *   SLA Compliance    tiket Selesai di periode yang tepat waktu / tiket Selesai di periode
 *   Rata-rata CSAT    rating tiket Selesai di periode
 *
 * Hanya tiket milik Staff yang login: query() selalu mengunci handler_id.
 */
final class StaffWorkReport
{
    public const PERIODS = ['7' => '7 hari terakhir', '14' => '14 hari terakhir', '30' => '30 hari terakhir', '90' => '90 hari terakhir', 'custom' => 'Rentang tanggal sendiri'];

    public const MAX_DAYS = 366;

    public function __construct(
        public readonly User $staff,
        public readonly string $period,
        public readonly Carbon $from,
        public readonly Carbon $to,
    ) {}

    public static function fromRequest(Request $request, User $staff): self
    {
        $period = array_key_exists((string) $request->query('period'), self::PERIODS) ? (string) $request->query('period') : '30';
        $today = now()->endOfDay();
        $from = $to = null;

        if ($period === 'custom') {
            [$from, $to] = [self::date($request->query('from')), self::date($request->query('to'))];

            if (! $from && ! $to) {
                $period = '30';
            } else {
                $from ??= $to->copy()->subDays(29);
                $to ??= now();
                if ($from->greaterThan($to)) {
                    [$from, $to] = [$to, $from];
                }
                $from = $from->copy()->startOfDay();
                $to = $to->copy()->endOfDay();
                if ($from->diffInDays($to) > self::MAX_DAYS) {
                    $from = $to->copy()->subDays(self::MAX_DAYS - 1)->startOfDay();
                }
            }
        }

        if ($period !== 'custom') {
            $to = $today;
            $from = $today->copy()->subDays((int) $period - 1)->startOfDay(); // 7 hari = hari ini + 6 hari sebelumnya
        }

        return new self($staff, $period, $from, $to);
    }

    /** Tiket Staff ini yang diambil ATAU ditutup pada periode. */
    public function query(): Builder
    {
        return Ticket::query()
            ->where('tickets.handler_id', $this->staff->id)
            ->where(fn (Builder $query) => $query
                ->whereBetween('tickets.assigned_at', [$this->from, $this->to])
                ->orWhereBetween('tickets.resolved_at', [$this->from, $this->to]));
    }

    /**
     * Ringkasan angka laporan. Persentase/rata-rata null = belum ada datanya (ditampilkan "–").
     *
     * @return array<string, int|float|null>
     */
    public function summary(): array
    {
        $tickets = $this->query()
            ->leftJoin('ratings', 'ratings.ticket_id', '=', 'tickets.id')
            ->get(['tickets.id', 'tickets.status', 'tickets.created_at', 'tickets.assigned_at', 'tickets.first_responded_at',
                'tickets.resolved_at', 'tickets.sla_target_hours', 'ratings.score as rating_score']);

        $inPeriod = fn (?Carbon $at) => $at !== null && $at->between($this->from, $this->to);
        $resolved = $tickets->filter(fn (Ticket $t) => $t->status === TicketStatus::Selesai && $inPeriod($t->resolved_at));
        $slaMet = $resolved->filter(fn (Ticket $t) => $t->slaMet());
        $rated = $resolved->filter(fn (Ticket $t) => $t->rating_score !== null);
        $responded = $tickets->filter(fn (Ticket $t) => $t->first_responded_at !== null);

        return [
            'total' => $tickets->count(),
            'diambil' => $tickets->filter(fn (Ticket $t) => $inPeriod($t->assigned_at))->count(),
            'selesai' => $resolved->count(),
            'ditolak' => $tickets->filter(fn (Ticket $t) => $t->status === TicketStatus::Ditolak && $inPeriod($t->resolved_at))->count(),
            'diproses' => $tickets->where('status', TicketStatus::Diproses)->count(),
            'avg_resolution_seconds' => self::average($resolved->map(fn (Ticket $t) => $t->resolved_at->getTimestamp() - $t->created_at->getTimestamp())),
            'avg_first_response_seconds' => self::average($responded->map(fn (Ticket $t) => $t->first_responded_at->getTimestamp() - $t->created_at->getTimestamp())),
            'responded' => $responded->count(),
            'sla_met' => $slaMet->count(),
            'sla_rate' => TicketMetrics::percent($slaMet->count(), $resolved->count()),
            'csat' => $rated->isEmpty() ? null : round($rated->avg(fn (Ticket $t) => (int) $t->rating_score), 2),
            'rated' => $rated->count(),
        ];
    }

    /** Query string yang sama, untuk tautan ekspor & paginasi. */
    public function toQuery(): array
    {
        return array_filter([
            'period' => $this->period,
            'from' => $this->period === 'custom' ? $this->from->format('Y-m-d') : null,
            'to' => $this->period === 'custom' ? $this->to->format('Y-m-d') : null,
        ]);
    }

    /** "1 Okt 2026 – 30 Okt 2026" */
    public function label(): string
    {
        return $this->from->translatedFormat('j M Y').' – '.$this->to->translatedFormat('j M Y');
    }

    private static function average(Collection $values): ?float
    {
        $values = $values->map(fn (int $seconds) => max(0, $seconds));

        return $values->isEmpty() ? null : round($values->avg(), 0);
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }
    }
}
