<?php

namespace App\Http\Controllers\Staff;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Rating;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /staff/dashboard — Dashboard Staff Fakultas (US-06, PRD 10.3). Semua angka dari data asli.
 *
 * 5 kartu PRD: Antrean (sesuai spesialisasi), Sedang Saya Proses, Selesai Hari Ini, Ditolak Bulan Ini,
 * Total Ditangani Bulan Ini (= tiket yang saya tutup bulan ini, Selesai + Ditolak).
 * Kartu ke-6 (tambahan): rata-rata rating CSAT dari tiket yang pernah saya tangani.
 *
 * Daftar: teguran Ping dari Admin yang belum dibaca (M4), tiket Mendekati/Melewati SLA (antrean + tiket saya),
 * dan 5 tiket yang sedang saya proses.
 */
class DashboardController extends Controller
{
    /** Berapa tiket antrean yang diperiksa untuk daftar SLA (antrean diurutkan dari yang tertua). */
    private const SLA_SCAN_LIMIT = 100;

    public function __invoke(Request $request): View
    {
        $staff = $request->user()->load('faculty', 'specialties');
        $today = now()->startOfDay();
        $month = now()->startOfMonth();

        $queue = Ticket::forStaff($staff)->where('status', TicketStatus::Menunggu);
        $mine = fn () => $staff->handledTickets();
        $closedSince = fn (TicketStatus $status, $since) => $mine()->where('status', $status)->where('resolved_at', '>=', $since)->count();

        $ratings = Rating::whereHas('ticket', fn ($query) => $query->where('handler_id', $staff->id));

        $stats = [
            'queue' => (clone $queue)->count(),
            'in_progress' => $mine()->where('status', TicketStatus::Diproses)->count(),
            'resolved_today' => $closedSince(TicketStatus::Selesai, $today),
            'rejected_month' => $closedSince(TicketStatus::Ditolak, $month),
            'resolved_month' => $closedSince(TicketStatus::Selesai, $month),
            'rating_avg' => $ratings->count() ? round((float) $ratings->avg('score'), 2) : null, // MySQL mengembalikan AVG sebagai teks desimal
            'rating_count' => $ratings->count(),
        ];
        $stats['handled_month'] = $stats['resolved_month'] + $stats['rejected_month'];

        $inProgress = $mine()->where('status', TicketStatus::Diproses)
            ->with(['category.parent', 'faculty', 'handler', 'reporter'])
            ->orderBy('created_at')
            ->get();

        // Tiket yang perlu perhatian: merah (lewat SLA) dulu, lalu kuning; di dalamnya batas SLA terdekat dulu.
        $slaAlerts = (clone $queue)->with(['category.parent', 'faculty', 'reporter'])->orderBy('created_at')->limit(self::SLA_SCAN_LIMIT)->get()
            ->concat($inProgress)
            ->filter(fn (Ticket $ticket) => in_array($ticket->slaState(), ['overdue', 'near'], true))
            ->sortBy([
                fn (Ticket $a, Ticket $b) => ($a->slaState() === 'overdue' ? 0 : 1) <=> ($b->slaState() === 'overdue' ? 0 : 1),
                fn (Ticket $a, Ticket $b) => $a->slaDeadline() <=> $b->slaDeadline(),
            ])
            ->take(5)
            ->values();

        // Teguran Ping dari Admin yang belum dibaca (notifikasi in-app, US-14 / F-NTF-06).
        // [M5] Lonceng Staff kini juga berisi notifikasi lain (batas thread), jadi kotak ini hanya mengambil Ping.
        $unreadPings = fn () => $staff->inAppNotifications()->where('is_read', false)->where('subject', 'like', 'Ping dari Admin%');
        $pings = $unreadPings()->with('ticket')
            ->orderByDesc('sent_at')->orderByDesc('id')->limit(5)->get();

        return view('staff.dashboard', [
            'user' => $staff,
            'pings' => $pings,
            'unreadPings' => $unreadPings()->count(),
            'stats' => $stats,
            'slaAlerts' => $slaAlerts,
            'inProgress' => $inProgress->sortByDesc('assigned_at')->take(5)->values(),
        ]);
    }
}
