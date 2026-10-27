<?php

namespace Tests\Feature\Dekan;

use App\Enums\TicketStatus;

/**
 * Dashboard Dekan: statistik fakultasnya saja, rumus KPI sama dengan Admin, tabel hasil kerja Staff.
 */
class DekanDashboardTest extends DekanTestCase
{
    public function test_dashboard_shows_faculty_statistics_and_staff_work_results(): void
    {
        // FITK: 2 selesai (1 tepat SLA 10 jam, 1 lewat SLA 30 jam; KRS SLA 24 jam), 1 ditolak, 1 diproses, 1 menunggu.
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(3), 'resolveAfter' => 10, 'rating' => 5]);
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(4), 'resolveAfter' => 30, 'rating' => 3]);
        $this->ticket(['status' => TicketStatus::Ditolak, 'created' => now()->subDays(2)]);
        $this->ticket(['status' => TicketStatus::Diproses, 'created' => now()->subDay(), 'handler' => $this->akademik2]);
        $this->ticket(['created' => now()->subHours(2)]);
        // FEBI: tidak boleh ikut dihitung.
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(3), 'handler' => $this->staffFebi,
            'category' => $this->cat('KRS belum di-ACC', $this->febi), 'title' => 'Tiket FEBI rahasia']);

        $response = $this->actingAs($this->dekan)->get('/dekan/dashboard')->assertOk();

        $kpi = $response->viewData('kpi');
        $this->assertSame([5, 2, 1, 1, 1], [$kpi['total'], $kpi['selesai'], $kpi['ditolak'], $kpi['diproses'], $kpi['menunggu']]);
        $this->assertSame(50.0, $kpi['sla_rate']);
        $this->assertSame(4.0, $kpi['csat']);
        $this->assertSame(['menunggu' => 1, 'diproses' => 1], $response->viewData('now'));

        $staff = collect($response->viewData('staffPerformance'))->keyBy(fn ($row) => $row['staff']->name);
        $this->assertSame([3, 2, 1, 0, 50.0, 4.0], [
            $staff['Sari Akademik']['handled'], $staff['Sari Akademik']['selesai'], $staff['Sari Akademik']['ditolak'],
            $staff['Sari Akademik']['diproses'], $staff['Sari Akademik']['sla_rate'], $staff['Sari Akademik']['csat'],
        ]);
        $this->assertSame(1, $staff['Rudi Akademik']['diproses']);
        $this->assertArrayNotHasKey('Fajar FEBI', $staff->all());

        $response->assertSee('Hasil Kerja Staff')->assertSee('Rata-rata CSAT')->assertSee('Masih diproses')
            ->assertSee('Sistem Akademik &amp; Perkuliahan', false)
            ->assertDontSee('Tiket FEBI rahasia');
    }

    public function test_period_switches_between_7_and_30_days(): void
    {
        $this->ticket(['created' => now()->subDays(3)]);
        $this->ticket(['created' => now()->subDays(20)]);

        $this->assertSame(2, $this->actingAs($this->dekan)->get('/dekan/dashboard')->viewData('kpi')['total']);
        $this->assertSame(1, $this->actingAs($this->dekan)->get('/dekan/dashboard?period=7')->viewData('kpi')['total']);
        $this->assertSame('30', $this->actingAs($this->dekan)->get('/dekan/dashboard?period=custom')->viewData('filters')->period);
        $this->assertCount(7, $this->actingAs($this->dekan)->get('/dekan/dashboard?period=7')->viewData('trend')['masuk']);
    }

    public function test_dekan_febi_sees_only_febi(): void
    {
        $this->ticket(['created' => now()->subDays(3)]);

        $this->assertSame(0, $this->actingAs($this->dekanFebi)->get('/dekan/dashboard')->viewData('kpi')['total']);
    }

    public function test_empty_faculty_dashboard_renders(): void
    {
        $this->actingAs($this->dekan)->get('/dekan/dashboard')->assertOk()->assertSee('Belum ada tiket');
    }
}
