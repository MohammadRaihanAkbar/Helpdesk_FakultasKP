<?php

namespace App\Http\Controllers\Staff;

use App\Enums\TicketStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

/**
 * GET /staff/queue — Antrean Tiket sesuai spesialisasi (US-07, F-TKS-01, PRD 4.6).
 *
 * Isi: tiket MENUNGGU di fakultas staff yang Kelompok Layanannya termasuk spesialisasinya
 * (Ticket::forStaff). Diurutkan FIFO: yang paling lama dibuat di atas (PRD US-07).
 *
 * Filter (query string): group (Kelompok Layanan dari spesialisasi), type (jenis pelapor),
 * from / to (rentang tanggal dibuat, Y-m-d), q (nomor tiket atau judul).
 */
class QueueController extends Controller
{
    public function index(Request $request): View
    {
        $staff = $request->user();
        $specialties = $staff->specialties()->orderBy('name')->get();

        $filters = [
            'group' => $specialties->firstWhere('id', $request->integer('group'))?->id,
            'type' => UserType::tryFrom((string) $request->query('type')),
            'from' => $this->date($request->query('from')),
            'to' => $this->date($request->query('to')),
            'q' => trim((string) $request->query('q')),
        ];

        $tickets = Ticket::forStaff($staff)
            ->where('status', TicketStatus::Menunggu)
            ->with(['category.parent', 'reporter', 'faculty'])
            ->when($filters['group'], fn ($query, $group) => $query->inGroup($group))
            ->when($filters['type'], fn ($query, $type) => $query->where('reporter_type', $type))
            ->when($filters['from'], fn ($query, $from) => $query->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($filters['to'], fn ($query, $to) => $query->where('created_at', '<=', $to->copy()->endOfDay()))
            ->search($filters['q'])
            ->orderBy('created_at')   // FIFO: tertua di atas
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('staff.queue', [
            'staff' => $staff,
            'tickets' => $tickets,
            'specialties' => $specialties,
            'filters' => $filters,
            'filtered' => $filters['group'] || $filters['type'] || $filters['from'] || $filters['to'] || $filters['q'] !== '',
        ]);
    }

    /** Tanggal dari input type="date" (Y-m-d). Isian yang tidak valid diabaikan, bukan error. */
    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
