<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\Reports\FacultyReport;
use App\Services\Reports\StaffWorkReport;
use App\Services\Reports\TicketExport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * [M6] Laporan Kerja Staff — hasil kerja Staff yang login sendiri (lihat StaffWorkReport).
 *
 *   GET /staff/laporan                      index   filter periode, kartu angka, daftar tiket (20 per halaman)
 *   GET /staff/laporan/export?format=csv    export  unduh CSV / Excel (format=xlsx), kolom sama dengan ekspor US-15
 *
 * Hanya tiket miliknya (handler_id = dirinya). Tidak ada parameter untuk melihat Staff lain.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $report = StaffWorkReport::fromRequest($request, $request->user());

        $tickets = $report->query()
            ->with(['category.parent', 'faculty', 'rating'])
            ->orderByRaw('COALESCE(tickets.resolved_at, tickets.assigned_at) DESC')
            ->orderByDesc('tickets.id')
            ->paginate(20)
            ->withQueryString();

        return view('staff.laporan', [
            'report' => $report,
            'summary' => $report->summary(),
            'tickets' => $tickets,
        ]);
    }

    public function export(Request $request, TicketExport $export): StreamedResponse|BinaryFileResponse
    {
        $report = StaffWorkReport::fromRequest($request, $request->user());
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';
        $name = sprintf('laporan-kerja-%s-%s-%s.%s', Str::slug($request->user()->name), $report->from->format('Ymd'), $report->to->format('Ymd'), $format);

        return FacultyReport::send($export, $report->query(), $name, $format, 'Laporan Kerja');
    }
}
