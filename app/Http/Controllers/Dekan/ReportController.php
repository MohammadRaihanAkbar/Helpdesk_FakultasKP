<?php

namespace App\Http\Controllers\Dekan;

use App\Services\Reports\FacultyReport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * [M6] Laporan & Ekspor untuk Dekan — halaman, filter, dan file CSV/Excel YANG SAMA dengan Laporan Admin (US-15),
 * lewat App\Services\Reports\FacultyReport. Bedanya:
 *   - nomor tiket tidak bisa diklik (Dekan tidak membuka detail tiket);
 *   - nama pelapor tiket kategori sensitif (Integritas Dosen) diganti "Dirahasiakan" di layar dan di file.
 *
 *   GET /dekan/laporan                     index
 *   GET /dekan/laporan/export?format=csv   export (format=xlsx untuk Excel)
 */
class ReportController extends DekanController
{
    public function index(Request $request, FacultyReport $report): View
    {
        return view('admin.reports.index', $report->page($request, $this->faculty($request), [
            'index' => 'dekan.laporan',
            'export' => 'dekan.laporan.export',
            'ticket' => null,
        ], maskSensitive: true));
    }

    public function export(Request $request, FacultyReport $report): StreamedResponse|BinaryFileResponse
    {
        return $report->download($request, $this->faculty($request), maskSensitive: true);
    }
}
