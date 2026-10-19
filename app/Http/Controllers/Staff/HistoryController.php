<?php

namespace App\Http\Controllers\Staff;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Ticket;
use App\Services\CategoryManager;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

/**
 * GET /staff/history — Riwayat Tiket Staff (US-09, F-TKS-06): tiket Selesai/Ditolak yang PERNAH ia tangani.
 * Detailnya (/staff/history/{ticket}) memakai Staff\TicketController@show.
 *
 * Filter (PRD US-09: "Rentang Tanggal dan Kategori"):
 *   from, to  rentang tanggal DITUTUP (resolved_at), Y-m-d
 *   month     YYYY-MM (filter lama M3, dipakai tautan kartu dashboard) -> diubah menjadi from/to bulan itu
 *   category  Kelompok Layanan ATAU Jenis Masalah (dari tiket yang pernah ia tutup)
 *   group     nama lama untuk category (M3), tetap diterima
 *   status    selesai | ditolak
 */
class HistoryController extends Controller
{
    public function index(Request $request, CategoryManager $categories): View
    {
        $staff = $request->user();

        [$from, $to] = [$this->date($request->query('from')), $this->date($request->query('to'))];
        if (! $from && ! $to && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))) {
            $month = Carbon::createFromFormat('!Y-m', $request->query('month'));
            [$from, $to] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        }
        if ($from && $to && $from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $status = TicketStatus::tryFrom((string) $request->query('status'));
        $status = in_array($status, TicketStatus::closedStatuses(), true) ? $status : null;

        // Pilihan kategori: kelompok dari tiket yang pernah ia tutup (spesialisasi bisa berubah) + Jenis Masalah-nya.
        $groupIds = Ticket::where('handler_id', $staff->id)->closed()
            ->join('categories', 'categories.id', '=', 'tickets.category_id')
            ->selectRaw('DISTINCT COALESCE(categories.parent_id, categories.id) as group_id')
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $categoryOptions = $staff->faculty_id ? $categories->filterOptions($staff->faculty_id, $groupIds) : [];

        $categoryId = $request->integer('category') ?: $request->integer('group');
        $category = array_key_exists($categoryId, $categoryOptions) ? Category::find($categoryId) : null;

        $tickets = $staff->handledTickets()
            ->closed()
            ->with(['category.parent', 'faculty', 'rating'])
            ->when($from, fn ($query, $from) => $query->where('resolved_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($query, $to) => $query->where('resolved_at', '<=', $to->copy()->endOfDay()))
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->when($category, fn ($query, $category) => $category->parent_id === null
                ? $query->inGroup($category->id)
                : $query->where('category_id', $category->id))
            ->orderByDesc('resolved_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('staff.history', [
            'tickets' => $tickets,
            'categoryOptions' => $categoryOptions,
            'filters' => ['from' => $from?->format('Y-m-d'), 'to' => $to?->format('Y-m-d'), 'status' => $status, 'category' => $category?->id],
        ]);
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
