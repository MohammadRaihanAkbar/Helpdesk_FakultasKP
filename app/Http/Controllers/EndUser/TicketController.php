<?php

namespace App\Http\Controllers\EndUser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Models\Ticket;
use App\Services\Tickets\TicketCategoryTree;
use App\Services\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Tiket milik pelapor (US-03, US-04, US-05B).
 *
 *   GET  /tickets               index   Tiket Saya: Menunggu & Diproses
 *   GET  /tickets/new           create  form buat tiket (?from={id} = Buat Ulang Tiket)
 *   POST /tickets               store
 *   GET  /tickets/{ticket}      show    detail + linimasa + thread (tiket yang sudah ditutup -> /history/{id})
 */
class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = $request->user()->reportedTickets()
            ->open()
            ->with(['category.parent', 'faculty', 'handler'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->query('q'));
                $query->where(fn ($inner) => $inner->where('ticket_number', 'like', "%{$q}%")->orWhere('title', 'like', "%{$q}%"));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('enduser.tickets.index', ['tickets' => $tickets, 'search' => $request->query('q')]);
    }

    public function create(Request $request, TicketCategoryTree $catalog): View
    {
        $user = $request->user();
        abort_if($user->user_type === null, 403, 'Jenis pelapor akun Anda belum diketahui, jadi belum bisa membuat tiket. Hubungi admin.');

        $faculties = $catalog->faculties();
        $from = $this->recreateSource($request);

        // Nilai awal dropdown: isian lama (validasi gagal) > tiket lama (Buat Ulang) > fakultas asal pelapor.
        $originFaculty = $faculties->firstWhere('id', $user->origin_faculty_id);
        $initial = [
            'faculty' => (string) old('faculty_id', $from?->faculty_id ?? $originFaculty?->id ?? ''),
            'group' => (string) old('group_id', $from?->group()->id ?? ''),
            'category' => (string) old('category_id', $from && ! $from->category->isGroup() ? $from->category_id : ''),
        ];

        return view('enduser.tickets.create', [
            'user' => $user,
            'faculties' => $faculties,
            'originFaculty' => $originFaculty,
            'tree' => $catalog->forUserType($user->user_type),
            'initial' => $initial,
            'from' => $from,
        ]);
    }

    public function store(StoreTicketRequest $request, TicketService $tickets): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_if($user->user_type === null, 403, 'Jenis pelapor akun Anda belum diketahui, jadi belum bisa membuat tiket. Hubungi admin.');

        $from = null;
        if ($request->filled('from')) {
            $from = Ticket::find($request->integer('from'));
            // Lampiran lama hanya boleh dibawa dari tiket milik sendiri yang memang boleh dibuat ulang.
            $from = $from && Gate::allows('recreate', $from) ? $from : null;
        }

        $ticket = $tickets->create(
            $user,
            $request->validated(),
            $request->file('attachments', []),
            $from,
            array_map('intval', $request->input('copy_attachments', [])),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $ticket->load('category.parent', 'attachments')->only([
                    'id', 'ticket_number', 'title', 'status', 'sla_target_hours', 'reporter_type', 'category', 'attachments', 'created_at',
                ]),
            ], 201);
        }

        return to_route('tickets.show', $ticket)
            ->with('status', "Tiket {$ticket->ticket_number} berhasil dibuat dan masuk antrean staff {$ticket->faculty->code}.");
    }

    public function show(Request $request, Ticket $ticket): View|RedirectResponse
    {
        Gate::authorize('view', $ticket);

        // Tiket yang sudah ditutup tampil di bawah menu Riwayat, yang masih terbuka di bawah Tiket Saya.
        if (! $ticket->isOpen() && $request->routeIs('tickets.show')) {
            return to_route('history.show', $ticket);
        }
        if ($ticket->isOpen() && $request->routeIs('history.show')) {
            return to_route('tickets.show', $ticket);
        }

        $user = $request->user();
        $ticket->load(['category.parent', 'faculty', 'handler', 'attachments', 'resolutionProofs', 'rating']);

        $comments = $ticket->comments()
            ->with(['user', 'attachments'])
            ->unless(Gate::allows('viewInternalNotes', $ticket), fn ($query) => $query->where('is_internal_note', false))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('enduser.tickets.show', [
            'ticket' => $ticket,
            'comments' => $comments,
            'publicCount' => $comments->where('is_internal_note', false)->count(),
            'canReply' => Gate::allows('reply', $ticket) && $ticket->isOpen(),
            'canRecreate' => Gate::allows('recreate', $ticket),
            'viewer' => $user,
        ]);
    }

    /** Tiket sumber untuk "Buat Ulang Tiket" (?from=id). Bukan milik sendiri -> 403; tidak memenuhi syarat -> diabaikan. */
    private function recreateSource(Request $request): ?Ticket
    {
        if (! $request->filled('from')) {
            return null;
        }

        $from = Ticket::with('category.parent', 'attachments')->findOrFail($request->integer('from'));
        Gate::authorize('view', $from);

        return Gate::allows('recreate', $from) ? $from : null;
    }
}
