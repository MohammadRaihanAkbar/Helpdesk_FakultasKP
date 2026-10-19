<?php

namespace App\Http\Controllers\EndUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /history — Riwayat Tiket pelapor: tiket Selesai & Ditolak (US-05, F-TKU-06).
 * Detailnya (/history/{ticket}) memakai TicketController@show.
 */
class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = $request->user()->reportedTickets()
            ->closed()
            ->with(['category.parent', 'faculty', 'rating'])
            ->orderByDesc('resolved_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('enduser.history.index', ['tickets' => $tickets]);
    }
}
