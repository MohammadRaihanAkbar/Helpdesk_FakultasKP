<?php

namespace App\Services\Reports;

use App\Enums\TicketStatus;
use App\Enums\UserType;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Perhitungan KPI helpdesk sesuai PRD 1.4 & US-15, untuk dashboard dan laporan Admin Fakultas.
 *
 * Semua angka dihitung dari KOLOM WAKTU tabel tickets (+ skor di ratings), tanpa tabel tambahan:
 *
 *   Ticket Claim Rate        = tiket yang sudah diambil (assigned_at terisi) / semua tiket            x 100%
 *   Rata-rata Time-to-Claim  = rata-rata (assigned_at - created_at) tiket yang sudah diambil
 *   Ticket Completion Rate   = tiket Selesai / tiket yang sudah ditutup (Selesai + Ditolak)             x 100%
 *   SLA Compliance Rate      = tiket Selesai yang resolved_at <= created_at + sla_target_hours / tiket Selesai x 100%
 *   Rata-rata Resolution Time= rata-rata (resolved_at - created_at) tiket Selesai
 *   CSAT Score               = rata-rata ratings.score tiket yang sudah dinilai
 *
 * Tiket Ditolak TIDAK ikut SLA Compliance & Resolution Time (PRD 8.2 tabel 6: "hanya memakai tiket berstatus Selesai").
 *
 * "Semua tiket" di sini = kelompok tiket yang dikirim lewat query (mis. tiket yang MASUK bulan ini atau pada
 * periode laporan). Selisih waktu dihitung di PHP, bukan di SQL, karena fungsi selisih waktu MySQL
 * (TIMESTAMPDIFF) dan SQLite (julianday) berbeda — dengan begini hasilnya sama di Laragon dan di test.
 */
class TicketMetrics
{
    /** Kolom yang diambil untuk perhitungan (cukup kecil untuk ribuan tiket per fakultas). */
    private const COLUMNS = [
        'tickets.id', 'tickets.status', 'tickets.reporter_type', 'tickets.category_id', 'tickets.handler_id',
        'tickets.created_at', 'tickets.assigned_at', 'tickets.resolved_at', 'tickets.sla_target_hours',
        'ratings.score as rating_score',
    ];

    /**
     * Ambil baris tiket (beserta skor rating) dari query.
     *
     * @return Collection<int, Ticket>
     */
    public function rows(Builder $query): Collection
    {
        return (clone $query)
            ->leftJoin('ratings', 'ratings.ticket_id', '=', 'tickets.id')
            ->get(self::COLUMNS)
            ->toBase();
    }

    /**
     * Ringkasan KPI dari sekumpulan baris tiket.
     *
     * Persentase & rata-rata bernilai null kalau belum ada datanya (ditampilkan "–", bukan 0%).
     *
     * @param  iterable<Ticket>  $rows
     * @return array<string, int|float|null>
     */
    public function summarize(iterable $rows): array
    {
        $rows = collect($rows);

        $claimed = $rows->filter(fn (Ticket $t) => $t->assigned_at !== null);
        $resolved = $rows->filter(fn (Ticket $t) => $t->status === TicketStatus::Selesai && $t->resolved_at !== null);
        $rejected = $rows->filter(fn (Ticket $t) => $t->status === TicketStatus::Ditolak);
        $closedCount = $resolved->count() + $rejected->count();
        $slaMet = $resolved->filter(fn (Ticket $t) => $t->resolved_at->lessThanOrEqualTo($t->created_at->copy()->addHours($t->sla_target_hours)));
        $rated = $rows->filter(fn (Ticket $t) => $t->rating_score !== null);

        return [
            'total' => $rows->count(),
            'menunggu' => $rows->where('status', TicketStatus::Menunggu)->count(),
            'diproses' => $rows->where('status', TicketStatus::Diproses)->count(),
            'selesai' => $resolved->count(),
            'ditolak' => $rejected->count(),
            'claimed' => $claimed->count(),
            'claim_rate' => self::percent($claimed->count(), $rows->count()),
            'avg_claim_seconds' => self::average($claimed->map(fn (Ticket $t) => self::seconds($t->created_at, $t->assigned_at))),
            'completion_rate' => self::percent($resolved->count(), $closedCount),
            'sla_met' => $slaMet->count(),
            'sla_rate' => self::percent($slaMet->count(), $resolved->count()),
            'avg_resolution_seconds' => self::average($resolved->map(fn (Ticket $t) => self::seconds($t->created_at, $t->resolved_at))),
            'csat' => $rated->isEmpty() ? null : round($rated->avg(fn (Ticket $t) => (int) $t->rating_score), 2),
            'rated' => $rated->count(),
        ];
    }

    /**
     * Jumlah tiket per satuan waktu (PRD US-10 tren 7 hari, US-15 per hari/minggu/bulan).
     * Setiap satuan dalam rentang tetap muncul walau 0, supaya grafik tidak "melompat".
     *
     * @param  iterable<Ticket>  $rows
     * @param  string  $column  kolom waktu yang dihitung: created_at (masuk) atau resolved_at (ditutup)
     * @return list<array{key: string, label: string, value: int}>
     */
    public function series(iterable $rows, Carbon $from, Carbon $to, string $unit, string $column = 'created_at', ?TicketStatus $status = null): array
    {
        $counts = collect($rows)
            ->filter(fn (Ticket $t) => $t->{$column} !== null && ($status === null || $t->status === $status))
            ->countBy(fn (Ticket $t) => self::bucketKey($t->{$column}, $unit));

        $buckets = [];
        $cursor = self::bucketStart($from, $unit);
        while ($cursor->lessThanOrEqualTo($to)) {
            $key = self::bucketKey($cursor, $unit);
            $buckets[] = ['key' => $key, 'label' => self::bucketLabel($cursor, $unit), 'value' => (int) ($counts[$key] ?? 0)];
            $cursor = match ($unit) {
                'week' => $cursor->copy()->addWeek(),
                'month' => $cursor->copy()->addMonthNoOverflow(),
                default => $cursor->copy()->addDay(),
            };
        }

        return $buckets;
    }

    /**
     * Distribusi per Kelompok Layanan, dengan rincian per Jenis Masalah (drill-down US-15).
     *
     * @param  iterable<Ticket>  $rows
     * @return list<array{group: Category, total: int, children: list<array{category: Category, total: int}>}>
     */
    public function byGroup(iterable $rows, int $facultyId): array
    {
        $categories = Category::forFaculty($facultyId)->get()->keyBy('id');
        $perCategory = collect($rows)->countBy('category_id');

        $groups = [];
        foreach ($perCategory as $categoryId => $total) {
            $category = $categories[$categoryId] ?? null;
            if (! $category) {
                continue;
            }
            $groupId = $category->parent_id ?? $category->id;
            $groups[$groupId] ??= ['group' => $categories[$groupId], 'total' => 0, 'children' => []];
            $groups[$groupId]['total'] += $total;
            if ($category->parent_id !== null) {
                $groups[$groupId]['children'][] = ['category' => $category, 'total' => $total];
            }
        }

        foreach ($groups as &$group) {
            usort($group['children'], fn ($a, $b) => $b['total'] <=> $a['total']);
        }

        return collect($groups)->sortByDesc('total')->values()->all();
    }

    /**
     * Distribusi per Jenis Pelapor dari snapshot tickets.reporter_type (US-15).
     *
     * @param  iterable<Ticket>  $rows
     * @return list<array{type: UserType, total: int}>
     */
    public function byReporterType(iterable $rows): array
    {
        $counts = collect($rows)->countBy(fn (Ticket $t) => $t->reporter_type?->value);

        return collect(UserType::cases())
            ->map(fn (UserType $type) => ['type' => $type, 'total' => (int) ($counts[$type->value] ?? 0)])
            ->all();
    }

    /**
     * Performa per Staff (US-15, PRD 10.4): tiket ditangani, rata-rata waktu penyelesaian, SLA compliance.
     * Semua Staff AKTIF fakultas ikut tampil (walau 0 tiket); Staff nonaktif hanya bila punya tiket di periode.
     *
     * @param  iterable<Ticket>  $rows
     * @return list<array{staff: User, handled: int, selesai: int, ditolak: int, diproses: int, avg_resolution_seconds: ?float, sla_rate: ?float}>
     */
    public function byStaff(iterable $rows, int $facultyId, ?User $only = null): array
    {
        $perStaff = collect($rows)->whereNotNull('handler_id')->groupBy('handler_id');

        $staff = User::where('role', 'staff')
            ->where('faculty_id', $facultyId)
            ->when($only, fn ($query, User $user) => $query->whereKey($user->id))
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $perStaff->keys()))
            ->orderBy('name')
            ->get();

        return $staff->map(function (User $user) use ($perStaff) {
            $summary = $this->summarize($perStaff[$user->id] ?? []);

            return [
                'staff' => $user,
                'handled' => $summary['total'],
                'diproses' => $summary['diproses'],
                'selesai' => $summary['selesai'],
                'ditolak' => $summary['ditolak'],
                'avg_resolution_seconds' => $summary['avg_resolution_seconds'],
                'sla_rate' => $summary['sla_rate'],
            ];
        })
            ->sortByDesc('handled')
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Helper kecil
    // ------------------------------------------------------------------

    public static function percent(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round($part / $whole * 100, 1);
    }

    private static function average(Collection $values): ?float
    {
        return $values->isEmpty() ? null : round($values->avg(), 0);
    }

    private static function seconds(Carbon $from, Carbon $to): int
    {
        return max(0, $to->getTimestamp() - $from->getTimestamp());
    }

    private static function bucketStart(Carbon $date, string $unit): Carbon
    {
        return match ($unit) {
            'week' => $date->copy()->startOfWeek(Carbon::MONDAY),
            'month' => $date->copy()->startOfMonth(),
            default => $date->copy()->startOfDay(),
        };
    }

    private static function bucketKey(Carbon $date, string $unit): string
    {
        return self::bucketStart($date, $unit)->format('Y-m-d');
    }

    private static function bucketLabel(Carbon $start, string $unit): string
    {
        return match ($unit) {
            'week' => $start->translatedFormat('j M'),   // minggu yang dimulai Senin tsb.
            'month' => $start->translatedFormat('M Y'),
            default => $start->translatedFormat('j M'),
        };
    }
}
