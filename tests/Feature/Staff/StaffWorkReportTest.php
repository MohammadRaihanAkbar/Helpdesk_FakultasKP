<?php

namespace Tests\Feature\Staff;

use App\Enums\TicketStatus;
use App\Services\Reports\TicketExport;
use Tests\Feature\AdminMonitoring\AdminTestCase;

/**
 * [M6] Laporan Kerja Staff (/staff/laporan): periode 7/14/30/90 hari + rentang sendiri, angka hasil kerja,
 * hanya tiket miliknya sendiri, dan ekspor berformat sama dengan ekspor Admin (US-15).
 */
class StaffWorkReportTest extends AdminTestCase
{
    private function csv(string $query = ''): array
    {
        $response = $this->actingAs($this->akademik)->get('/staff/laporan/export?format=csv'.$query)->assertOk();

        return array_map(fn ($line) => str_getcsv($line, ',', '"', ''), array_values(array_filter(explode("\n", substr($response->streamedContent(), 3)))));
    }

    public function test_summary_counts_the_staff_own_work_in_the_period(): void
    {
        // Sari (akademik): 2 selesai (tepat & lewat SLA 24 jam), 1 ditolak, 1 masih diproses — semua dalam 30 hari.
        $fast = $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(5), 'claimAfter' => 1, 'resolveAfter' => 10, 'rating' => 5]);
        $fast->update(['first_responded_at' => $fast->created_at->copy()->addHours(2)]);
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(6), 'resolveAfter' => 30, 'rating' => 3]);
        $this->ticket(['status' => TicketStatus::Ditolak, 'created' => now()->subDays(4)]);
        $this->ticket(['status' => TicketStatus::Diproses, 'created' => now()->subDays(1)]);
        // Bukan miliknya / di luar periode:
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(3), 'handler' => $this->akademik2, 'title' => 'Tiket Rudi']);
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(60), 'title' => 'Tiket lama Sari']);

        $response = $this->actingAs($this->akademik)->get('/staff/laporan')->assertOk();
        $s = $response->viewData('summary');

        $this->assertSame([4, 4, 2, 1, 1], [$s['total'], $s['diambil'], $s['selesai'], $s['ditolak'], $s['diproses']]);
        $this->assertSame(50.0, $s['sla_rate']);
        $this->assertSame(4.0, $s['csat']);
        $this->assertSame(7200.0, $s['avg_first_response_seconds']);
        $this->assertSame((10 + 30) / 2 * 3600.0, $s['avg_resolution_seconds']);

        $response->assertSee('Laporan Kerja Saya')->assertSee('Tepat waktu')->assertSee('Lewat target')
            ->assertDontSee('Tiket Rudi')->assertDontSee('Tiket lama Sari');
    }

    public function test_period_presets_and_custom_range(): void
    {
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(3)]);
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(10)]);
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(40)]);
        $total = fn (string $query) => $this->actingAs($this->akademik)->get('/staff/laporan'.$query)->viewData('summary')['total'];

        $this->assertSame(1, $total('?period=7'));
        $this->assertSame(2, $total('?period=14'));
        $this->assertSame(2, $total('?period=30'));
        $this->assertSame(3, $total('?period=90'));
        $this->assertSame(2, $total('?period=bukan-pilihan')); // bawaan 30 hari

        $from = now()->subDays(12)->format('Y-m-d');
        $to = now()->subDays(8)->format('Y-m-d');
        $this->assertSame(1, $total("?period=custom&from={$from}&to={$to}"));
        $this->assertSame(1, $total("?period=custom&from={$to}&to={$from}")); // tanggal terbalik ditukar
    }

    public function test_ticket_claimed_earlier_but_closed_in_the_period_is_included(): void
    {
        // Diambil 10 hari lalu, baru selesai 2 hari lalu -> ikut laporan 7 hari sebagai Selesai, tetapi tidak sebagai "Diambil".
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(10), 'claimAfter' => 1, 'resolveAfter' => 24 * 8]);

        $s = $this->actingAs($this->akademik)->get('/staff/laporan?period=7')->viewData('summary');

        $this->assertSame([1, 0, 1], [$s['total'], $s['diambil'], $s['selesai']]);
    }

    public function test_export_uses_the_us15_columns_and_only_own_tickets(): void
    {
        $mine = $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(3), 'rating' => 4]);
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(3), 'handler' => $this->akademik2]);

        $rows = $this->csv('&period=7');

        $this->assertSame(TicketExport::HEADERS, $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame([$mine->ticket_number, 'Sari Akademik', '4'], [$rows[1][0], $rows[1][11], $rows[1][10]]);

        $xlsx = $this->actingAs($this->akademik)->get('/staff/laporan/export?format=xlsx')->assertOk();
        $this->assertStringContainsString('laporan-kerja-sari-akademik-', $xlsx->headers->get('Content-Disposition'));
    }

    public function test_only_staff_can_open_the_work_report(): void
    {
        $this->actingAs($this->akademik)->get('/staff/dashboard')->assertSee('Laporan Kerja');

        foreach ([$this->admin, $this->mahasiswa] as $other) {
            $this->actingAs($other)->get('/staff/laporan')->assertForbidden();
            $this->actingAs($other)->get('/staff/laporan/export')->assertForbidden();
        }
    }

    public function test_empty_report_renders(): void
    {
        $this->actingAs($this->tanpaSpesialisasi)->get('/staff/laporan')->assertOk()->assertSee('Belum ada tiket pada periode ini');
    }
}
