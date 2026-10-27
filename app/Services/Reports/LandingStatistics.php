<?php

namespace App\Services\Reports;

use App\Models\Faculty;
use App\Models\Ticket;
use App\Services\CategoryCatalogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * [M6] Statistik "Tren Tiket" di halaman depan publik (/), untuk SEMUA fakultas aktif.
 *
 * Aturan privasi (halaman ini bisa dibuka siapa saja tanpa login):
 *   - HANYA angka agregat: tidak ada nomor tiket, judul, nama pelapor, atau nama Staff;
 *   - kelompok sensitif "Integritas Dosen" (PRD 2.7 aturan 10) TIDAK PERNAH tampil di rincian tren. Tiketnya hanya
 *     ikut dihitung di angka total, sama seperti peringkat SLA fakultas di dashboard pelapor (M5).
 *
 * Isi:
 *   summary   total tiket, % selesai, rata-rata waktu penyelesaian, SLA compliance (rumus TicketMetrics, PRD 1.4)
 *   volume    tiket masuk per hari, 30 hari terakhir
 *   trending  5 Kelompok Layanan terbanyak 30 hari terakhir + perubahan dibanding 30 hari sebelumnya
 *   issues    5 Jenis Masalah terbanyak 30 hari terakhir
 *   ranking   peringkat Performa SLA Fakultas (TicketMetrics::facultyRanking, cache yang sama dengan dashboard pelapor)
 *
 * Disimpan di cache (bawaan 15 menit, config helpdesk.landing.cache_seconds): angkanya sama untuk semua pengunjung,
 * jadi tidak perlu dihitung ulang setiap kali halaman dibuka. Isinya array biasa supaya aman di cache database.
 */
class LandingStatistics
{
    public const CACHE_KEY = 'landing-statistics';

    public const WINDOW_DAYS = 30;

    public function __construct(private readonly TicketMetrics $metrics) {}

    public function get(): array
    {
        return Cache::remember(self::CACHE_KEY, (int) config('helpdesk.landing.cache_seconds', 900), fn () => $this->compute());
    }

    public function compute(?Carbon $now = null): array
    {
        $now ??= now();
        $faculties = Faculty::where('is_active', true)->orderBy('id')->get(['id', 'code', 'name']);
        $facultyIds = $faculties->modelKeys();

        $rows = $this->metrics->rows(Ticket::query()->whereIn('tickets.faculty_id', $facultyIds));
        $kpi = $this->metrics->summarize($rows);

        $from = $now->copy()->subDays(self::WINDOW_DAYS - 1)->startOfDay();
        $recent = $rows->filter(fn (Ticket $t) => $t->created_at->greaterThanOrEqualTo($from));

        return [
            'summary' => [
                'total' => $kpi['total'],
                'selesai' => $kpi['selesai'],
                'resolved_rate' => TicketMetrics::percent($kpi['selesai'], $kpi['total']),
                'avg_resolution_seconds' => $kpi['avg_resolution_seconds'],
                'sla_rate' => $kpi['sla_rate'],
                'last_30_days' => $recent->count(),
            ],
            'volume' => $this->metrics->series($recent, $from, $now, 'day'),
            ...$this->trends($facultyIds, $now),
            'ranking' => Cache::remember(TicketMetrics::RANKING_CACHE_KEY, TicketMetrics::RANKING_CACHE_SECONDS, fn () => $this->metrics->facultyRanking()),
            'faculties' => $faculties->map(fn (Faculty $f) => ['code' => $f->code, 'name' => $f->name])->all(),
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * Kelompok Layanan & Jenis Masalah yang sedang ramai. Kategori dari fakultas berbeda dengan NAMA sama
     * (katalog standar: "Akun, Jaringan & IT" ada di keenam fakultas) digabung menjadi satu baris.
     *
     * @param  list<int>  $facultyIds
     * @return array{trending: list<array>, issues: list<array>}
     */
    private function trends(array $facultyIds, Carbon $now): array
    {
        $currentFrom = $now->copy()->subDays(self::WINDOW_DAYS);
        $previousFrom = $now->copy()->subDays(self::WINDOW_DAYS * 2);

        $tickets = DB::table('tickets')
            ->join('categories as c', 'c.id', '=', 'tickets.category_id')
            ->leftJoin('categories as p', 'p.id', '=', 'c.parent_id')
            ->whereIn('tickets.faculty_id', $facultyIds)
            ->where('tickets.created_at', '>=', $previousFrom)
            ->get([
                'tickets.created_at',
                DB::raw('COALESCE(p.name, c.name) as group_name'),
                DB::raw('COALESCE(p.is_standard, c.is_standard) as group_standard'),
                DB::raw('CASE WHEN c.parent_id IS NULL THEN NULL ELSE c.name END as issue_name'),
            ])
            // Kelompok sensitif tidak pernah dirinci di halaman publik.
            ->reject(fn ($t) => (bool) $t->group_standard && in_array($t->group_name, CategoryCatalogService::SENSITIVE_GROUPS, true))
            ->map(fn ($t) => (array) $t + ['current' => Carbon::parse($t->created_at)->greaterThanOrEqualTo($currentFrom)]);

        $current = $tickets->where('current', true);
        $previous = $tickets->where('current', false)->countBy('group_name');
        $currentTotal = max(1, $current->count());

        $trending = $current->countBy('group_name')
            ->map(fn (int $count, string $name) => [
                'name' => $name,
                'count' => $count,
                'previous' => (int) ($previous[$name] ?? 0),
                'share' => round($count / $currentTotal * 100, 1),
                // null = belum ada tiket di 30 hari sebelumnya (ditampilkan "baru")
                'change' => isset($previous[$name]) && $previous[$name] > 0 ? (int) round(($count - $previous[$name]) / $previous[$name] * 100) : null,
            ])
            ->sortBy([['count', 'desc'], ['name', 'asc']])
            ->take(5)
            ->values()
            ->all();

        $issues = $current->whereNotNull('issue_name')->countBy(fn (array $t) => $t['issue_name'].' · '.$t['group_name'])
            ->map(function (int $count, string $key) {
                [$issue, $group] = explode(' · ', $key, 2);

                return ['name' => $issue, 'group' => $group, 'count' => $count];
            })
            ->sortBy([['count', 'desc'], ['name', 'asc']])
            ->take(5)
            ->values()
            ->all();

        return ['trending' => $trending, 'issues' => $issues];
    }
}
