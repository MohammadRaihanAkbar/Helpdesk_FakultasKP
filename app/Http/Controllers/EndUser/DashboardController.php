<?php

namespace App\Http\Controllers\EndUser;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Services\Reports\TicketMetrics;
use App\Services\Tickets\TicketNotifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard Pelapor (US-02): 4 kartu ringkasan, tombol Buat Tiket, dan 5 tiket terbaru — semua dari data asli.
 * [M5] + notifikasi baru (balasan Staff & perubahan status, F-NTF-05) dengan penanda "Balasan baru" di tiket terbaru,
 *      + Peringkat Performa SLA Fakultas (F-TKU-08), di-cache 10 menit karena sama untuk semua pelapor.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, TicketMetrics $metrics): View
    {
        $user = $request->user()->load('faculty', 'originFaculty');

        // Satu query: jumlah tiket per status -> ['menunggu' => 2, 'diproses' => 1, ...]
        $perStatus = $user->reportedTickets()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (TicketStatus $status) => (int) ($perStatus[$status->value] ?? 0);

        // Notifikasi lonceng yang belum dibaca (balasan Staff, status berubah). Dibaca = diklik atau tiketnya dibuka.
        $unread = fn () => $user->inAppNotifications()->where('is_read', false);

        return view('enduser.dashboard', [
            'user' => $user,
            'notifications' => $unread()->with('ticket')->orderByDesc('sent_at')->orderByDesc('id')->limit(5)->get(),
            'unreadCount' => $unread()->count(),
            // Tiket yang punya balasan Staff belum dibaca -> badge "Balasan baru" di daftar Tiket Terbaru.
            'slaRanking' => Cache::remember(TicketMetrics::RANKING_CACHE_KEY, TicketMetrics::RANKING_CACHE_SECONDS, fn () => $metrics->facultyRanking()),
            'repliedTicketIds' => $unread()->where('subject', 'like', TicketNotifier::REPLY_SUBJECT.'%')->pluck('ticket_id')->unique()->values()->all(),
            'stats' => [
                'total' => (int) $perStatus->sum(),
                'menunggu' => $count(TicketStatus::Menunggu),
                'diproses' => $count(TicketStatus::Diproses),
                'selesai' => $count(TicketStatus::Selesai),
            ],
            'latest' => $user->reportedTickets()
                ->with(['category.parent', 'faculty', 'handler'])
                ->latest()
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
