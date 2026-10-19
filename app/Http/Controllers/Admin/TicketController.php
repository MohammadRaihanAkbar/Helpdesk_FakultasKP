<?php

namespace App\Http\Controllers\Admin;

use App\Models\PingLog;
use App\Models\Ticket;
use App\Services\Tickets\PingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Detail tiket untuk Admin Fakultas — HANYA BACA (US-16, PRD 2.3).
 *
 *   GET /admin/tickets/{ticket}        show   data tiket, thread + catatan internal, lampiran, bukti, rating,
 *                                             linimasa + riwayat Ping, dan form Ping (tiket terbuka)
 *   GET /admin/tickets/{ticket}/pings  pings  riwayat Ping dalam JSON (PRD 9.2 GET /tickets/:id/pings)
 *
 * Admin boleh melihat identitas pelapor kategori sensitif (PRD 2.7 aturan 10), tetapi tidak bisa
 * membalas, mengambil, memindahkan, atau menutup tiket.
 */
class TicketController extends AdminController
{
    public function show(Request $request, Ticket $ticket, PingService $pings): View
    {
        $this->facultyId($request);
        Gate::authorize('view', $ticket);

        $ticket->load(['category.parent', 'faculty', 'handler', 'reporter', 'attachments', 'resolutionProofs.uploader', 'rating', 'pingLogs.admin', 'pingLogs.staff']);

        $comments = $ticket->comments()->with(['user', 'attachments'])->orderBy('created_at')->orderBy('id')->get();

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'comments' => $comments,
            'publicCount' => $comments->where('is_internal_note', false)->count(),
            'recipients' => $pings->recipients($ticket),
            'viewer' => $request->user(),
        ]);
    }

    public function pings(Request $request, Ticket $ticket): JsonResponse
    {
        $this->facultyId($request);
        Gate::authorize('ping', $ticket);

        $logs = $ticket->pingLogs()->with(['admin', 'staff'])->orderByDesc('created_at')->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $logs->map(fn (PingLog $log) => [
                'id' => $log->id,
                'admin' => ['id' => $log->admin->id, 'name' => $log->admin->name],
                'staff' => ['id' => $log->staff->id, 'name' => $log->staff->name],
                'message' => $log->message,
                'created_at' => $log->created_at->toIso8601String(),
            ])->values(),
        ]);
    }
}
