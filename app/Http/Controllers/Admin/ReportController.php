<?php

namespace App\Http\Controllers\Admin;

use App\Services\Reports\FacultyReport;
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
 * [M6] Isi halaman & ekspor dipindah ke App\Services\Reports\FacultyReport, karena Dekan memakai laporan yang sama.
 */
class ReportController extends AdminController
{
    public function index(Request $request, FacultyReport $report): View
    {
        $this->facultyId($request);

        return view('admin.reports.index', $report->page($request, $request->user()->faculty, [
            'index' => 'admin.reports.index',
            'export' => 'admin.reports.export',
            'ticket' => 'admin.tickets.show',
        ]));
    }

    public function export(Request $request, FacultyReport $report): StreamedResponse|BinaryFileResponse
    {
        $this->facultyId($request);

        return $report->download($request, $request->user()->faculty);
    }
}
