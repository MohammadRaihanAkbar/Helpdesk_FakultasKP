<?php

namespace App\Http\Controllers\EndUser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreCommentRequest;
use App\Models\Ticket;
use App\Services\Tickets\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * POST /tickets/{ticket}/comments — pelapor membalas thread (US-04).
 * Pesan ke-21 -> 409 THREAD_LIMIT_REACHED; tiket ditutup -> 409 THREAD_CLOSED (lihat CommentService).
 */
class TicketCommentController extends Controller
{
    public function store(StoreCommentRequest $request, Ticket $ticket, CommentService $comments): RedirectResponse|JsonResponse
    {
        Gate::authorize('reply', $ticket);

        $comment = $comments->post($ticket, $request->user(), $request->validated('body'), $request->file('attachments', []));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $comment->load('attachments'),
                'meta' => ['public_count' => $comments->publicCount($ticket), 'limit' => Ticket::THREAD_LIMIT],
            ], 201);
        }

        return to_route('tickets.show', $ticket)->withFragment('thread')->with('status', 'Pesan terkirim.');
    }
}
