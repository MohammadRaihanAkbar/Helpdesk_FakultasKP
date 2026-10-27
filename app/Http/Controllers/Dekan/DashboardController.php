<?php

namespace App\Http\Controllers\Dekan;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\TicketMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [M6] GET /dekan/dashboard — Dashboard statistik hasil kerja fakultas untuk Dekan (hanya baca).
 *
 * Periode ?period=7 | 30 (bawaan 30 hari terakhir), memakai ReportFilters yang sama dengan Laporan Admin:
 * semua KPI dihitung dari tiket yang MASUK pada periode itu, dengan rumus TicketMetrics (PRD 1.4) yang sama
 * dengan dashboard Admin Fakultas dan Super Admin.
 *
 *   Kartu      : total tiket periode, menunggu & diproses (saat ini), selesai, ditolak
 *   KPI        : SLA Compliance, rata-rata Resolution Time, rata-rata Time-to-Claim, CSAT, Completion Rate
 *   Grafik     : tren tiket masuk & selesai per hari; proporsi per Kelompok Layanan
 *   Hasil kerja: tabel per Staff (ditangani, selesai, ditolak, masih diproses, rata-rata penyelesaian, SLA, CSAT)
 */
class DashboardController extends DekanController
{
    public const PERIODS = ['7' => '7 hari', '30' => '30 hari'];

    public function __invoke(Request $request, TicketMetrics $metrics): View
    {
        $user = $request->user();
        $faculty = $this->faculty($request);

        // Dashboard hanya 7/30 hari; rentang lain lewat halaman Laporan.
        $request->query->set('period', array_key_exists((string) $request->query('period'), self::PERIODS) ? (string) $request->query('period') : '30');
        $filters = ReportFilters::fromRequest($request, $faculty->id);

        $rows = $metrics->rows($filters->query());
        $resolved = $metrics->rows(Ticket::query()->where('tickets.faculty_id', $faculty->id)->whereBetween('tickets.resolved_at', [$filters->from, $filters->to]));
        $open = fn (TicketStatus $status) => Ticket::where('faculty_id', $faculty->id)->where('status', $status)->count();

        return view('dekan.dashboard', [
            'user' => $user,
            'faculty' => $faculty,
            'filters' => $filters,
            'kpi' => $metrics->summarize($rows),
            'now' => ['menunggu' => $open(TicketStatus::Menunggu), 'diproses' => $open(TicketStatus::Diproses)],
            'trend' => [
                'masuk' => $metrics->series($rows, $filters->from, $filters->to, 'day'),
                'selesai' => $metrics->series($resolved, $filters->from, $filters->to, 'day', 'resolved_at', TicketStatus::Selesai),
            ],
            'groups' => $metrics->byGroup($rows, $faculty->id),
            'staffPerformance' => $metrics->byStaff($rows, $faculty->id),
        ]);
    }
}
