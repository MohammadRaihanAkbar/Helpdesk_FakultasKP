<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Services\CategoryManager;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\TicketExport;
use App\Services\Reports\TicketMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan & Analitik Fakultas (US-15, F-ADM-07/08, sitemap /admin/reports).
 *
 *   GET /admin/reports                    index   kartu KPI, grafik volume, distribusi kelompok & jenis pelapor,
 *                                                 performa per Staff, tabel tiket
 *   GET /admin/reports/export?format=csv  export  unduh CSV   (filter sama dengan halaman)
 *   GET /admin/reports/export?format=xlsx export  unduh Excel (filter sama dengan halaman)
 *
 * Semua data HANYA dari fakultas admin yang login (ReportFilters selalu mengunci faculty_id).
 */
class ReportController extends AdminController
{
    public function index(Request $request, TicketMetrics $metrics, CategoryManager $categories): View
    {
        $facultyId = $this->facultyId($request);
        $filters = ReportFilters::fromRequest($request, $facultyId);
        $unit = $filters->unit($request->query('unit'));

        $rows = $metrics->rows($filters->query());

        $tickets = $filters->query()
            ->with(['reporter', 'category.parent', 'handler', 'rating'])
            ->withCount('resolutionProofs')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reports.index', [
            'faculty' => $request->user()->faculty,
            'filters' => $filters,
            'unit' => $unit,
            'kpi' => $metrics->summarize($rows),
            'volume' => $metrics->series($rows, $filters->from, $filters->to, $unit),
            'groups' => $metrics->byGroup($rows, $facultyId),
            'reporterTypes' => $metrics->byReporterType($rows),
            'staffPerformance' => $metrics->byStaff($rows, $facultyId, $filters->staff),
            'tickets' => $tickets,
            'categoryOptions' => $categories->filterOptions($facultyId),
            'staffOptions' => User::where('role', Role::Staff)->where('faculty_id', $facultyId)->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function export(Request $request, TicketExport $export): StreamedResponse|BinaryFileResponse
    {
        $facultyId = $this->facultyId($request);
        $filters = ReportFilters::fromRequest($request, $facultyId);
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';

        $code = $request->user()->faculty->code;
        $name = sprintf('laporan-tiket-%s-%s-%s.%s', strtolower($code), $filters->from->format('Ymd'), $filters->to->format('Ymd'), $format);

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($export, $filters) {
                $out = fopen('php://output', 'w');
                $export->writeCsv($filters->query(), $out);
                fclose($out);
            }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $export->writeXlsx($filters->query(), $path, "Tiket {$code}");

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
