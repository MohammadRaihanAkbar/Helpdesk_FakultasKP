<?php

namespace App\Http\Controllers\Staff;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateTicketStatusRequest;
use App\Models\Notification;
use App\Models\Ticket;
use App\Services\Tickets\TicketWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Tiket dari sisi Staff (US-07, US-08).
 *
 *   GET   /staff/tickets                  index   Tiket Saya: Diproses milik staff yang login
 *   GET   /staff/tickets/{ticket}         show    detail + thread + aksi (tiket ditutup -> /staff/history/{id})
 *   PATCH /staff/tickets/{ticket}/assign  assign  Ambil Tiket (Menunggu -> Diproses)
 *   PATCH /staff/tickets/{ticket}/status  status  Selesai (wajib bukti) / Ditolak (wajib alasan)
 *
 * Semua aksi juga bisa dipanggil dengan header "Accept: application/json" (Postman) dan menjawab
 * dengan format PRD 9.3: {"success": true, "data": {...}, "message": "..."}.
 */
class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $tickets = $request->user()->handledTickets()
            ->where('status', TicketStatus::Diproses)
            ->with(['category.parent', 'faculty', 'handler', 'reporter'])
            ->withCount(['publicComments'])
            ->search($search)
            ->orderBy('created_at') // yang paling lama (paling dekat batas SLA) di atas
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('staff.tickets.index', ['tickets' => $tickets, 'search' => $search]);
    }

    public function show(Request $request, Ticket $ticket): View|RedirectResponse
    {
        Gate::authorize('view', $ticket);

        // Sama seperti sisi pelapor: tiket terbuka di bawah "Tiket Saya", tiket ditutup di bawah "Riwayat".
        if (! $ticket->isOpen() && $request->routeIs('staff.tickets.show')) {
            return to_route('staff.history.show', $ticket);
        }
        if ($ticket->isOpen() && $request->routeIs('staff.history.show')) {
            return to_route('staff.tickets.show', $ticket);
        }

        $ticket->load(['category.parent', 'faculty', 'handler', 'reporter', 'attachments', 'resolutionProofs.uploader', 'rating', 'pingLogs.admin', 'pingLogs.staff']);

        // Teguran Ping untuk tiket ini dianggap sudah dibaca begitu Staff membuka tiketnya (badge lonceng berkurang).
        Notification::markTicketRead($request->user(), $ticket);

        // Staff yang boleh melihat tiket juga boleh membaca catatan internal (F-TKS-08).
        $comments = $ticket->comments()
            ->with(['user', 'attachments'])
            ->unless(Gate::allows('viewInternalNotes', $ticket), fn ($query) => $query->where('is_internal_note', false))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('staff.tickets.show', [
            'ticket' => $ticket,
            'comments' => $comments,
            'publicCount' => $comments->where('is_internal_note', false)->count(),
            'canClaim' => $ticket->status === TicketStatus::Menunggu && Gate::allows('claim', $ticket),
            'canHandle' => $ticket->status === TicketStatus::Diproses && Gate::allows('handle', $ticket),
            'showReporter' => Gate::allows('viewReporterIdentity', $ticket),
            'viewer' => $request->user(),
        ]);
    }

    public function assign(Request $request, Ticket $ticket, TicketWorkflowService $workflow): RedirectResponse|JsonResponse
    {
        Gate::authorize('claim', $ticket);

        $oldStatus = $ticket->status;
        $ticket = $workflow->assign($ticket, $request->user());
        $message = "Tiket {$ticket->ticket_number} berhasil diambil dan berstatus Diproses.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'status' => $ticket->status->value,
                    'old_status' => $oldStatus->value,
                    'handler' => ['id' => $ticket->handler->id, 'name' => $ticket->handler->name],
                    'assigned_at' => $ticket->assigned_at->toIso8601String(),
                    'created_at' => $ticket->created_at->toIso8601String(),
                ],
                'message' => $message,
            ]);
        }

        return to_route('staff.tickets.show', $ticket)->with('status', $message);
    }

    public function status(UpdateTicketStatusRequest $request, Ticket $ticket, TicketWorkflowService $workflow): RedirectResponse|JsonResponse
    {
        Gate::authorize('handle', $ticket);

        $oldStatus = $ticket->status;
        $target = $request->targetStatus();

        $ticket = $workflow->changeStatus(
            $ticket,
            $request->user(),
            $target,
            $request->proofFiles(),
            $target === TicketStatus::Ditolak ? $request->rejectionReason() : null,
        );

        $message = "Status tiket berhasil diubah menjadi {$ticket->status->label()}.";

        if ($request->expectsJson()) {
            $ticket->load('handler', 'resolutionProofs');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'status' => $ticket->status->value,
                    'old_status' => $oldStatus->value,
                    'assigned_at' => $ticket->assigned_at?->toIso8601String(),
                    'resolved_at' => $ticket->resolved_at?->toIso8601String(),
                    'rejection_reason' => $ticket->rejection_reason,
                    'sla_target_hours' => $ticket->sla_target_hours,
                    'sla_met' => $ticket->slaMet(),
                    'handler' => ['id' => $ticket->handler->id, 'name' => $ticket->handler->name],
                    'resolution_proofs' => $ticket->resolutionProofs->map->only(['id', 'file_name', 'mime_type', 'file_size'])->values(),
                ],
                'message' => $message,
            ]);
        }

        return to_route('staff.history.show', $ticket)->with('status', "Tiket {$ticket->ticket_number} ditutup: {$ticket->status->label()}.");
    }
}
