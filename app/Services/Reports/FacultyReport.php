<?php

namespace App\Services\Reports;

use App\Enums\Role;
use App\Models\Faculty;
use App\Models\User;
use App\Services\CategoryManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * [M6] Isi halaman "Laporan & Analitik" + unduhan CSV/Excel SATU fakultas (US-15).
 *
 * Dulu (M4) semuanya ada di Admin\ReportController. Sejak M6 halaman yang sama juga dibuka Dekan (/dekan/laporan),
 * jadi logikanya dipindah ke service ini supaya dua controller (Admin & Dekan) tetap tipis dan angkanya PASTI sama:
 * filter (ReportFilters), KPI (TicketMetrics), dan ekspor (TicketExport) yang sama persis.
 *
 * Bedanya hanya di $routes (tujuan form/unduhan/tautan tiket) dan $maskSensitive (Dekan tidak melihat nama pelapor
 * tiket kategori sensitif). View-nya tetap resources/views/admin/reports/index.blade.php.
 */
class FacultyReport
{
    public function __construct(
        private readonly TicketMetrics $metrics,
        private readonly CategoryManager $categories,
        private readonly TicketExport $export,
    ) {}

    /**
     * Data untuk view admin.reports.index.
     *
     * @param  array{index: string, export: string, ticket: ?string}  $routes  nama route; ticket = null -> nomor tiket tanpa tautan
     */
    public function page(Request $request, Faculty $faculty, array $routes, bool $maskSensitive = false): array
    {
        $filters = ReportFilters::fromRequest($request, $faculty->id);
        $unit = $filters->unit($request->query('unit'));

        $rows = $this->metrics->rows($filters->query());

        $tickets = $filters->query()
            ->with(['reporter', 'category.parent', 'handler', 'rating'])
            ->withCount('resolutionProofs')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return [
            'faculty' => $faculty,
            'filters' => $filters,
            'unit' => $unit,
            'kpi' => $this->metrics->summarize($rows),
            'volume' => $this->metrics->series($rows, $filters->from, $filters->to, $unit),
            'groups' => $this->metrics->byGroup($rows, $faculty->id),
            'reporterTypes' => $this->metrics->byReporterType($rows),
            'staffPerformance' => $this->metrics->byStaff($rows, $faculty->id, $filters->staff),
            'tickets' => $tickets,
            'categoryOptions' => $this->categories->filterOptions($faculty->id),
            'staffOptions' => User::where('role', Role::Staff)->where('faculty_id', $faculty->id)->orderBy('name')->get(['id', 'name', 'is_active']),
            'routes' => $routes,
            'maskSensitive' => $maskSensitive,
        ];
    }

    /** Unduh CSV (bawaan) atau Excel (?format=xlsx) dengan filter yang sama dengan halaman. */
    public function download(Request $request, Faculty $faculty, bool $maskSensitive = false): StreamedResponse|BinaryFileResponse
    {
        $filters = ReportFilters::fromRequest($request, $faculty->id);
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';
        $export = $this->export->maskSensitiveReporters($maskSensitive);

        $name = sprintf('laporan-tiket-%s-%s-%s.%s', strtolower($faculty->code), $filters->from->format('Ymd'), $filters->to->format('Ymd'), $format);

        return self::send($export, $filters->query(), $name, $format, "Tiket {$faculty->code}");
    }

    /**
     * Kirim hasil ekspor sebagai unduhan (dipakai juga laporan kerja Staff, M6).
     */
    public static function send(TicketExport $export, $query, string $name, string $format, string $sheetName): StreamedResponse|BinaryFileResponse
    {
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($export, $query) {
                $out = fopen('php://output', 'w');
                $export->writeCsv($query, $out);
                fclose($out);
            }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $export->writeXlsx($query, $path, $sheetName);

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
