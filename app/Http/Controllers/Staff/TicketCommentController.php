<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreCommentRequest;
use App\Models\Ticket;
use App\Services\Tickets\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * POST /staff/tickets/{ticket}/comments — staff penangan membalas thread atau menulis Catatan Internal
 * (US-08, F-TKS-04, F-TKS-08).
 *
 *   is_internal_note=1 -> catatan internal: tidak terlihat pelapor, tidak dihitung batas 20,
 *                         tetap bisa ditulis walau thread publik sudah penuh.
 *   balasan publik PERTAMA staff penangan mengisi tickets.first_responded_at (sekali saja) — di CommentService.
 *
 * Hanya staff penangan (TicketPolicy::reply). Staff lain yang berspesialisasi sama hanya bisa melihat.
 */
class TicketCommentController extends Controller
{
    public function store(StoreCommentRequest $request, Ticket $ticket, CommentService $comments): RedirectResponse|JsonResponse
    {
        Gate::authorize('reply', $ticket);

        $internal = $request->boolean('is_internal_note');
        $comment = $comments->post($ticket, $request->user(), $request->validated('body'), $request->file('attachments', []), $internal);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $comment->load('attachments'),
                'meta' => ['public_count' => $comments->publicCount($ticket), 'limit' => Ticket::THREAD_LIMIT],
            ], 201);
        }

        return to_route('staff.tickets.show', $ticket)
            ->withFragment('thread')
            ->with('status', $internal ? 'Catatan internal tersimpan (tidak terlihat oleh pelapor).' : 'Pesan terkirim ke pelapor.');
    }
}
