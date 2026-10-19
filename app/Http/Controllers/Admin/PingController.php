<?php

namespace App\Http\Controllers\Admin;

use App\Models\Ticket;
use App\Services\Tickets\PingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * POST /admin/tickets/{ticket}/ping — Ping / Ingatkan Staff (US-14, PRD 9.3 POST /tickets/:id/ping).
 *
 * Isian: message (opsional, maks. 500 karakter).
 * Browser  -> kembali ke halaman sebelumnya dengan pesan hijau.
 * Postman  -> JSON persis contoh PRD 9.3:
 *   {"success": true, "data": {"ticket_id", "pinged_staff": [{id, name}], "pinged_at", "ping_log_ids"}, "message": "..."}
 */
class PingController extends AdminController
{
    public function store(Request $request, Ticket $ticket, PingService $pings): RedirectResponse|JsonResponse
    {
        $this->facultyId($request);
        Gate::authorize('ping', $ticket);

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:'.PingService::MESSAGE_MAX],
        ], [], ['message' => 'pesan Ping']);

        $result = $pings->ping($ticket, $request->user(), $data['message'] ?? null);
        $count = $result['staff']->count();
        $message = "Notifikasi teguran berhasil dikirim ke {$count} staff terkait.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'ticket_id' => $ticket->id,
                    'pinged_staff' => $result['staff']->map(fn ($staff) => ['id' => $staff->id, 'name' => $staff->name])->values(),
                    'pinged_at' => $result['pinged_at']->toIso8601String(),
                    'ping_log_ids' => $result['logs']->pluck('id')->values(),
                ],
                'message' => $message,
            ]);
        }

        return back()->with('status', "Ping untuk {$ticket->ticket_number} terkirim ke {$count} staff: ".$result['staff']->pluck('name')->implode(', ').'.');
    }
}
