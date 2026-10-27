<?php

namespace Tests\Feature\Dekan;

use App\Enums\TicketStatus;
use App\Services\Reports\TicketExport;

/**
 * Laporan & Ekspor Dekan: halaman & file yang sama dengan Laporan Admin (kolom US-15), hanya fakultasnya,
 * tanpa tautan detail tiket, nama pelapor tiket sensitif disamarkan.
 */
class DekanReportTest extends DekanTestCase
{
    public function test_export_has_the_same_prd_columns_as_admin_and_only_own_faculty(): void
    {
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(2), 'title' => 'KRS FITK', 'rating' => 4]);
        $this->ticket(['created' => now()->subDays(2), 'category' => $this->cat('KRS belum di-ACC', $this->febi), 'title' => 'KRS FEBI']);

        $rows = $this->csv($this->dekan);

        $this->assertSame(TicketExport::HEADERS, $rows[0]);
        $this->assertCount(2, $rows); // judul + 1 tiket FITK
        $this->assertSame('Ahmad Mahasiswa', $rows[1][1]);
        $this->assertSame('4', $rows[1][10]);

        $admin = $this->actingAs($this->admin)->get('/admin/reports/export?format=csv')->streamedContent();
        $dekan = $this->actingAs($this->dekan)->get('/dekan/laporan/export?format=csv')->streamedContent();
        $this->assertSame($admin, $dekan); // tiket non-sensitif: isi file identik dengan milik Admin
    }

    public function test_xlsx_export_and_filename(): void
    {
        $this->ticket(['created' => now()->subDays(2)]);

        $response = $this->actingAs($this->dekan)->get('/dekan/laporan/export?format=xlsx&period=7')->assertOk();

        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('laporan-tiket-fitk-', $response->headers->get('Content-Disposition'));
    }

    public function test_sensitive_reporter_is_masked_for_dekan_but_not_for_admin(): void
    {
        $this->ticket(['created' => now()->subDays(1), 'category' => $this->cat('Integritas Dosen'), 'handler' => $this->dekanat,
            'status' => TicketStatus::Diproses, 'title' => 'Dosen tidak hadir']);

        $this->assertSame(TicketExport::MASKED_REPORTER, $this->csv($this->dekan)[1][1]);
        $this->assertStringContainsString('Ahmad Mahasiswa', $this->actingAs($this->admin)->get('/admin/reports/export?format=csv')->streamedContent());

        $this->actingAs($this->dekan)->get('/dekan/laporan')->assertOk()
            ->assertSee(TicketExport::MASKED_REPORTER)
            ->assertDontSee('Ahmad Mahasiswa');
    }

    public function test_report_page_uses_dekan_routes_and_no_ticket_links(): void
    {
        $ticket = $this->ticket(['created' => now()->subDays(2)]);

        $this->actingAs($this->dekan)->get('/dekan/laporan?period=7')->assertOk()
            ->assertSee($ticket->ticket_number)
            ->assertSee(route('dekan.laporan.export', ['period' => '7', 'format' => 'csv']))
            ->assertDontSee(route('admin.tickets.show', $ticket), false)
            ->assertDontSee('/admin/reports', false);
    }

    public function test_filters_apply_to_dekan_export(): void
    {
        $this->ticket(['created' => now()->subDays(2)]);
        $this->ticket(['created' => now()->subDays(20)]);

        $this->assertCount(2, $this->csv($this->dekan, '&period=7'));
        $this->assertCount(3, $this->csv($this->dekan, '&period=30'));
    }
}
