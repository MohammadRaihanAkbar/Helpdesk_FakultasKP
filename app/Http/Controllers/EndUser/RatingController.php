<?php

namespace App\Http\Controllers\EndUser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreRatingRequest;
use App\Models\Ticket;
use App\Services\Tickets\RatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * POST /tickets/{ticket}/rating — pelapor memberi rating CSAT pada tiket Selesai miliknya (US-05, PRD 9.2 Ratings).
 * Kedua kalinya -> 409 RATING_ALREADY_EXISTS; tiket belum Selesai -> 409 RATING_NOT_ALLOWED (RatingService).
 */
class RatingController extends Controller
{
    public function store(StoreRatingRequest $request, Ticket $ticket, RatingService $ratings): RedirectResponse|JsonResponse
    {
        Gate::authorize('rate', $ticket);

        $rating = $ratings->rate($ticket, $request->user(), (int) $request->validated('score'), $request->validated('comment'));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $rating->only(['id', 'ticket_id', 'score', 'comment', 'created_at']),
                'message' => 'Terima kasih atas feedback Anda.',
            ], 201);
        }

        return to_route('history.show', $ticket)->with('status', 'Terima kasih atas feedback Anda.');
    }
}
