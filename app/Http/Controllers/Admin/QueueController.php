<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Enums\UserType;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\CategoryManager;
use App\Services\Tickets\PingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

/**
 * GET /admin/queue — Monitor Antrean Fakultas (US-16, F-ADM-05, sitemap PRD 7.3).
 *
 * Bawaan: semua tiket AKTIF (Menunggu & Diproses) di fakultas admin, tertua di atas (paling lama menganggur).
 * Admin hanya MEMANTAU dan mengirim Ping. Tidak ada tombol ambil/pindah/tutup tiket (PRD 2.3, US-16).
 *
 * Filter (query string):
 *   status    aktif (bawaan) | menunggu | diproses | selesai | ditolak | semua
 *   sla       overdue (melewati SLA) | near (mendekati SLA) — hanya untuk tiket terbuka
 *   category  id Kelompok Layanan atau Jenis Masalah
 *   type      jenis pelapor (mahasiswa/dosen/alumni)
 *   staff     id Staff penangan
 *   from, to  rentang tanggal dibuat (Y-m-d)
 *   q         nomor tiket atau judul
 */
class QueueController extends AdminController
{
    public const STATUS_OPTIONS = [
        'aktif' => 'Aktif (Menunggu & Diproses)',
        'menunggu' => 'Menunggu',
        'diproses' => 'Diproses',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
        'semua' => 'Semua status',
    ];

    public const SLA_OPTIONS = ['overdue' => 'Melewati SLA', 'near' => 'Mendekati SLA'];

    public function index(Request $request, CategoryManager $categories, PingService $pings): View
    {
        $facultyId = $this->facultyId($request);

        $staffOptions = User::where('role', Role::Staff)->where('faculty_id', $facultyId)->orderBy('name')->get(['id', 'name', 'is_active']);
        $categoryOptions = $categories->filterOptions($facultyId);

        $filters = [
            // Tanpa pilihan status: tiket aktif saja. Tapi kalau mencari nomor (kotak cari di top bar), cari di semua status.
            'status' => array_key_exists((string) $request->query('status'), self::STATUS_OPTIONS)
                ? (string) $request->query('status')
                : (trim((string) $request->query('q')) !== '' ? 'semua' : 'aktif'),
            'sla' => array_key_exists((string) $request->query('sla'), self::SLA_OPTIONS) ? (string) $request->query('sla') : null,
            'category' => array_key_exists($request->integer('category'), $categoryOptions) ? $request->integer('category') : null,
            'type' => UserType::tryFrom((string) $request->query('type')),
            'staff' => $staffOptions->firstWhere('id', $request->integer('staff'))?->id,
            'from' => $this->date($request->query('from')),
            'to' => $this->date($request->query('to')),
            'q' => trim((string) $request->query('q')),
        ];

        $query = Ticket::query()
            ->where('tickets.faculty_id', $facultyId)
            ->with(['category.parent', 'reporter', 'handler', 'faculty'])
            ->withCount('pingLogs');

        match ($filters['status']) {
            'aktif' => $query->open(),
            'semua' => null,
            default => $query->where('status', TicketStatus::from($filters['status'])),
        };

        $query
            ->when($filters['category'], fn (Builder $q, int $id) => $this->filterCategory($q, Category::find($id)))
            ->when($filters['type'], fn (Builder $q, UserType $type) => $q->where('reporter_type', $type))
            ->when($filters['staff'], fn (Builder $q, int $id) => $q->where('handler_id', $id))
            ->when($filters['from'], fn (Builder $q, Carbon $from) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($filters['to'], fn (Builder $q, Carbon $to) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->search($filters['q']);

        // Filter SLA dihitung di PHP (created_at + sla_target_hours) supaya sama persis dengan badge merah/kuning
        // dan tidak bergantung fungsi tanggal MySQL/SQLite. Hanya tiket terbuka yang punya status SLA.
        if ($filters['sla']) {
            $ids = (clone $query)->setEagerLoads([])->open()->get(['id', 'status', 'created_at', 'sla_target_hours'])
                ->filter(fn (Ticket $ticket) => $ticket->slaState() === $filters['sla'])
                ->modelKeys();
            $query->whereKey($ids);
        }

        $tickets = $query->orderBy('created_at')->orderBy('id')->paginate(15)->withQueryString();

        return view('admin.queue', [
            'tickets' => $tickets,
            'filters' => $filters,
            'filtered' => $filters['status'] !== 'aktif' || $filters['sla'] || $filters['category'] || $filters['type']
                || $filters['staff'] || $filters['from'] || $filters['to'] || $filters['q'] !== '',
            'categoryOptions' => $categoryOptions,
            'staffOptions' => $staffOptions,
            'recipientCounts' => $pings->recipientCounts($tickets->getCollection(), $facultyId),
        ]);
    }

    private function filterCategory(Builder $query, ?Category $category): void
    {
        if ($category) {
            $category->parent_id === null ? $query->inGroup($category->id) : $query->where('category_id', $category->id);
        }
    }

    /** Tanggal dari input type="date" (Y-m-d). Isian tidak valid diabaikan. */
    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }
    }
}
