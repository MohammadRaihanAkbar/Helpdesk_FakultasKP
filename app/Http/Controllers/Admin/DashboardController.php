<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Reports\TicketMetrics;
use App\Services\StaffSpecialtyService;
use App\Services\Tickets\PingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /admin/dashboard — Dashboard Admin Fakultas (US-10, F-DSB-03, PRD 10.4). Semua angka dari data asli.
 *
 * Kartu operasional (US-10):
 *   Tiket Masuk Bulan Ini, Menunggu di Antrean (saat ini), Sedang Diproses (saat ini),
 *   Selesai Hari Ini (resolved_at hari ini), Ditolak Bulan Ini (resolved_at bulan ini).
 * KPI bulan ini (PRD 1.4) dari tiket yang MASUK bulan ini (lihat TicketMetrics):
 *   SLA Compliance Rate, CSAT, Rata-rata Time-to-Claim, Ticket Claim Rate, Ticket Completion Rate.
 * Lalu: Tiket Menganggur (Menunggu + lewat SLA, tombol Ping), tren 7 hari, distribusi per Kelompok Layanan,
 * performa Staff bulan ini, dan peringatan "Kelompok tanpa Staff".
 */
class DashboardController extends AdminController
{
    /** Maksimal baris "Tiket Menganggur" di dashboard; selebihnya lewat tautan ke Monitor Antrean. */
    private const IDLE_LIMIT = 5;

    public function __invoke(Request $request, StaffSpecialtyService $specialties, TicketMetrics $metrics, PingService $pings): View
    {
        $user = $request->user()->load('faculty', 'originFaculty');

        if ($user->faculty_id === null) {
            return view('admin.dashboard', ['user' => $user, 'coverage' => collect(), 'ready' => false]);
        }

        $facultyId = $user->faculty_id;
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $tickets = fn () => Ticket::query()->where('tickets.faculty_id', $facultyId);

        // KPI bulan ini: tiket yang dibuat sejak tanggal 1 bulan ini.
        $monthRows = $metrics->rows($tickets()->where('tickets.created_at', '>=', $monthStart));
        $kpi = $metrics->summarize($monthRows);

        $cards = [
            'masuk_bulan_ini' => $kpi['total'],
            'menunggu' => $tickets()->where('status', TicketStatus::Menunggu)->count(),
            'diproses' => $tickets()->where('status', TicketStatus::Diproses)->count(),
            'selesai_hari_ini' => $tickets()->where('status', TicketStatus::Selesai)->where('resolved_at', '>=', $now->copy()->startOfDay())->count(),
            'ditolak_bulan_ini' => $tickets()->where('status', TicketStatus::Ditolak)->where('resolved_at', '>=', $monthStart)->count(),
        ];

        // Tren 7 hari terakhir (US-10): tiket masuk per hari + tiket Selesai per hari.
        $weekStart = $now->copy()->subDays(6)->startOfDay();
        $created = $metrics->rows($tickets()->where('tickets.created_at', '>=', $weekStart));
        $resolved = $metrics->rows($tickets()->where('tickets.resolved_at', '>=', $weekStart));
        $trend = [
            'masuk' => $metrics->series($created, $weekStart, $now, 'day'),
            'selesai' => $metrics->series($resolved, $weekStart, $now, 'day', 'resolved_at', TicketStatus::Selesai),
        ];

        // Tiket Menganggur (US-10): masih Menunggu dan sudah melewati target SLA. Tertua di atas.
        $idle = $tickets()->where('status', TicketStatus::Menunggu)
            ->with(['category.parent', 'reporter', 'handler'])
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Ticket $ticket) => $ticket->slaState() === 'overdue')
            ->values();

        return view('admin.dashboard', [
            'user' => $user,
            'ready' => true,
            'cards' => $cards,
            'kpi' => $kpi,
            'trend' => $trend,
            'idle' => $idle->take(self::IDLE_LIMIT),
            'idleTotal' => $idle->count(),
            'recipientCounts' => $pings->recipientCounts($idle->take(self::IDLE_LIMIT), $facultyId),
            'groups' => $metrics->byGroup($monthRows, $facultyId),
            'staffPerformance' => $metrics->byStaff($monthRows, $facultyId),
            'coverage' => $specialties->coverage($facultyId),
        ]);
    }
}
