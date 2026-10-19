<?php

namespace App\Http\Controllers\Staff;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * GET /staff/history — Riwayat Tiket Staff (US-09, F-TKS-06): tiket Selesai/Ditolak yang PERNAH ia tangani.
 * Detailnya (/staff/history/{ticket}) memakai Staff\TicketController@show.
 *
 * Filter: month (YYYY-MM, bulan tiket ditutup), status (selesai/ditolak), group (Kelompok Layanan).
 */
class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        $staff = $request->user();

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? Carbon::createFromFormat('!Y-m', $request->query('month'))
            : null;
        $status = TicketStatus::tryFrom((string) $request->query('status'));
        $status = in_array($status, TicketStatus::closedStatuses(), true) ? $status : null;

        // Pilihan filter kelompok: kelompok dari tiket yang pernah ia tutup (spesialisasi bisa berubah).
        $groups = Category::whereIn('id', Ticket::where('handler_id', $staff->id)->closed()
            ->join('categories', 'categories.id', '=', 'tickets.category_id')
            ->selectRaw('COALESCE(categories.parent_id, categories.id)'))
            ->orderBy('name')
            ->get(['id', 'name']);
        $group = $groups->firstWhere('id', $request->integer('group'))?->id;

        $tickets = $staff->handledTickets()
            ->closed()
            ->with(['category.parent', 'faculty', 'rating'])
            ->when($month, fn ($query, $month) => $query->whereBetween('resolved_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]))
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->when($group, fn ($query, $group) => $query->inGroup($group))
            ->orderByDesc('resolved_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('staff.history', [
            'tickets' => $tickets,
            'groups' => $groups,
            'filters' => ['month' => $month?->format('Y-m'), 'status' => $status, 'group' => $group],
        ]);
    }
}
