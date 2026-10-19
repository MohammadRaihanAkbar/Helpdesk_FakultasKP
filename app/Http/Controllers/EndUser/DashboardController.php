<?php

namespace App\Http\Controllers\EndUser;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard Pelapor (US-02): 4 kartu ringkasan, tombol Buat Tiket, dan 5 tiket terbaru — semua dari data asli.
 * Peringkat Performa SLA Fakultas menyusul (butuh data tiket Selesai dari alur Staff, M3).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('faculty', 'originFaculty');

        // Satu query: jumlah tiket per status -> ['menunggu' => 2, 'diproses' => 1, ...]
        $perStatus = $user->reportedTickets()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (TicketStatus $status) => (int) ($perStatus[$status->value] ?? 0);

        return view('enduser.dashboard', [
            'user' => $user,
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
