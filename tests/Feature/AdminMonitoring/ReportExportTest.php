<?php

namespace Tests\Feature\AdminMonitoring;

use App\Enums\TicketStatus;
use App\Models\User;
use App\Services\Reports\TicketExport;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Laporan & Ekspor (US-15): filter periode/kategori/staff/status, KPI, dan isi + kolom file CSV/XLSX.
 */
class ReportExportTest extends AdminTestCase
{
    /** Kolom persis PRD US-15. */
    private const PRD_COLUMNS = [
        'No Tiket', 'Pelapor', 'Jenis Pelapor', 'Kelompok Layanan', 'Jenis Masalah', 'Status', 'Tanggal Dibuat',
        'Tanggal Diambil', 'Target Waktu', 'Tanggal Selesai/Ditolak', 'Rating', 'Handler (Staff)', 'Bukti Penyelesaian',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return list<list<string>> baris CSV tanpa BOM */
    private function csv(string $query = ''): array
    {
        $response = $this->actingAs($this->admin)->get('/admin/reports/export?format=csv'.$query)->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=laporan-tiket-fitk-', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content); // BOM agar Excel membaca UTF-8

        return array_map(fn ($line) => str_getcsv($line, ',', '"', ''), array_filter(explode("\n", substr($content, 3))));
    }

    public function test_csv_has_exact_prd_columns_and_values(): void
    {
        $this->assertSame(self::PRD_COLUMNS, TicketExport::HEADERS);

        $done = $this->ticket(['status' => TicketStatus::Selesai, 'created' => '2026-10-10 08:00', 'claimAfter' => 2, 'resolveAfter' => 20, 'rating' => 5, 'category' => $this->cat('Nilai belum muncul')]);
        $done->resolutionProofs()->create(['file_name' => 'bukti-1.pdf', 'file_path' => 'p/1.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1, 'uploaded_by' => $this->akademik->id]);
        $done->resolutionProofs()->create(['file_name' => 'bukti-2.png', 'file_path' => 'p/2.png', 'mime_type' => 'image/png', 'file_size' => 1, 'uploaded_by' => $this->akademik->id]);
        $waiting = $this->ticket(['created' => '2026-10-15 09:30', 'category' => $this->cat('Fasilitas, Sarana & Prasarana')]);

        $rows = $this->csv();

        $this->assertSame(self::PRD_COLUMNS, $rows[0]);
        $this->assertCount(3, $rows);
        // Terbaru di atas.
        $this->assertSame([$waiting->ticket_number, 'Ahmad Mahasiswa', 'Mahasiswa', 'Fasilitas, Sarana & Prasarana', '-', 'Menunggu', '2026-10-15 09:30', '', '72 jam', '', '', '', ''], $rows[1]);
        $this->assertSame([$done->ticket_number, 'Ahmad Mahasiswa', 'Mahasiswa', 'Sistem Akademik & Perkuliahan', 'Nilai belum muncul', 'Selesai', '2026-10-10 08:00', '2026-10-10 10:00', '72 jam', '2026-10-11 04:00', '5', 'Sari Akademik', 'bukti-1.pdf; bukti-2.png'], $rows[2]);
    }

    public function test_csv_neutralises_spreadsheet_formulas(): void
    {
        $evil = User::factory()->mahasiswa($this->fitk)->create(['name' => '=HYPERLINK("http://x")']);
        $this->ticket(['reporter' => $evil, 'created' => '2026-10-15 09:30']);

        $this->assertSame("'=HYPERLINK(\"http://x\")", $this->csv()[1][1]);
    }

    public function test_xlsx_has_same_columns_and_rows(): void
    {
        $this->ticket(['status' => TicketStatus::Ditolak, 'created' => '2026-10-12 08:00', 'claimAfter' => 1, 'resolveAfter' => 5, 'title' => 'Ditolak']);
        $this->ticket(['created' => '2026-10-16 08:00']);

        $response = $this->actingAs($this->admin)->get('/admin/reports/export?format=xlsx')->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $reader = new Reader;
        $reader->open($response->getFile()->getPathname());
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $this->assertSame('Tiket FITK', $sheet->getName());
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();

        $this->assertSame(self::PRD_COLUMNS, $rows[0]);
        $this->assertCount(3, $rows);
        $this->assertSame('Menunggu', $rows[1][5]);
        $this->assertSame(['Ditolak', '2026-10-12 09:00', '24 jam', '2026-10-12 13:00', 'Sari Akademik'], [$rows[2][5], $rows[2][7], $rows[2][8], $rows[2][9], $rows[2][11]]);
    }

    public function test_period_and_filters_apply_to_page_and_export(): void
    {
        $this->ticket(['title' => 'Dalam 7 hari', 'created' => '2026-10-18 08:00']);
        $this->ticket(['title' => 'Dua minggu lalu', 'created' => '2026-10-06 08:00', 'status' => TicketStatus::Selesai, 'handler' => $this->akademik2, 'category' => $this->cat('Nilai belum muncul')]);
        $this->ticket(['title' => 'Dua bulan lalu', 'created' => '2026-08-20 08:00', 'status' => TicketStatus::Selesai]);

        $titles = fn (string $q) => collect($this->actingAs($this->admin)->get('/admin/reports?'.$q)->assertOk()->viewData('tickets')->items())->pluck('title')->all();
        $csvCount = fn (string $q) => count($this->csv('&'.$q)) - 1;

        $this->assertSame(['Dalam 7 hari'], $titles('period=7'));
        $this->assertSame(['Dalam 7 hari', 'Dua minggu lalu'], $titles('period=30'));
        $this->assertSame(['Dalam 7 hari', 'Dua minggu lalu'], $titles('')); // bawaan 30 hari
        $this->assertSame(['Dua bulan lalu'], $titles('period=custom&from=2026-08-01&to=2026-08-31'));
        $this->assertSame(['Dua bulan lalu'], $titles('period=custom&from=2026-08-31&to=2026-08-01')); // tanggal terbalik ditukar
        $this->assertSame(['Dua minggu lalu'], $titles('status=selesai'));
        $this->assertSame(['Dua minggu lalu'], $titles('staff='.$this->akademik2->id));
        $this->assertSame(['Dua minggu lalu'], $titles('category='.$this->cat('Nilai belum muncul')->id));
        $this->assertSame(['Dalam 7 hari', 'Dua minggu lalu'], $titles('category='.$this->cat('Sistem Akademik & Perkuliahan')->id));

        $this->assertSame(1, $csvCount('period=7'));
        $this->assertSame(1, $csvCount('period=custom&from=2026-08-01&to=2026-08-31'));
        $this->assertSame(1, $csvCount('status=selesai'));
        $this->assertSame(0, $csvCount('period=7&status=ditolak'));
    }

    public function test_report_page_shows_kpis_charts_and_staff_performance(): void
    {
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => '2026-10-10 08:00', 'claimAfter' => 3, 'resolveAfter' => 20, 'rating' => 4]);
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => '2026-10-11 08:00', 'claimAfter' => 1, 'resolveAfter' => 30, 'handler' => $this->akademik2]);
        $dosen = User::factory()->dosen($this->fitk)->create();
        $this->ticket(['reporter' => $dosen, 'category' => $this->cat('Jadwal Mengajar'), 'created' => '2026-10-19 08:00']);

        $response = $this->actingAs($this->admin)->get('/admin/reports?unit=week')->assertOk();

        $response->assertViewHas('kpi', fn ($kpi) => $kpi['total'] === 3 && $kpi['sla_rate'] === 50.0 && $kpi['avg_resolution_seconds'] == 25 * 3600
            && $kpi['avg_claim_seconds'] == 2 * 3600 && $kpi['csat'] === 4.0 && $kpi['claim_rate'] === 66.7 && $kpi['completion_rate'] === 100.0);
        $response->assertViewHas('unit', 'week');
        $response->assertViewHas('reporterTypes', fn ($types) => collect($types)->pluck('total', 'type.value')->all() === ['mahasiswa' => 2, 'dosen' => 1, 'alumni' => 0]);
        $response->assertSee('Volume tiket masuk per minggu')
            ->assertSee('Proporsi per Kelompok Layanan')
            ->assertSee('Jadwal Mengajar')
            ->assertSee('Performa per Staff')
            ->assertSee('Rudi Akademik')
            ->assertSee('1 hari 1 jam')   // rata-rata resolution 25 jam
            ->assertSee('Unduh CSV')
            ->assertSee('Unduh Excel');
    }
}
