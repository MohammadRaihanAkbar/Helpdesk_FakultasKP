<?php

namespace Tests\Feature\AdminMonitoring;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Reports\TicketMetrics;
use Illuminate\Support\Carbon;

/**
 * Perhitungan KPI PRD 1.4 dengan data terkontrol (tanggal dikunci lewat Carbon::setTestNow).
 *
 * Data bulan Oktober 2026 (FITK), "sekarang" = 20 Okt 2026 12:00:
 *   A  Selesai  KRS (SLA 24j)  diambil +2j, selesai +20j  -> sesuai SLA, rating 5
 *   B  Selesai  KRS (SLA 24j)  diambil +4j, selesai +30j  -> MELEWATI SLA, rating 3
 *   C  Ditolak  KRS            diambil +6j, ditutup +8j
 *   D  Diproses KRS            diambil +12j
 *   E  Menunggu KRS            dibuat 18 Okt 10:00 -> sudah lewat 24j -> "menganggur"
 *   F  Selesai  September (di luar bulan ini) -> tidak ikut KPI bulan ini
 *
 *   Claim Rate      = 4 diambil / 5 tiket            = 80%
 *   Time-to-Claim   = (2+4+6+12)/4 jam                = 6 jam
 *   Completion Rate = 2 selesai / 3 ditutup          = 66,7%
 *   SLA Compliance  = 1 sesuai / 2 selesai           = 50%
 *   Resolution Time = (20+30)/2 jam                   = 25 jam
 *   CSAT            = (5+3)/2                         = 4,0
 */
class KpiTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 12:00:00');

        $this->ticket(['status' => TicketStatus::Selesai, 'created' => '2026-10-05 08:00', 'claimAfter' => 2, 'resolveAfter' => 20, 'rating' => 5, 'title' => 'Tiket A']);
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => '2026-10-06 08:00', 'claimAfter' => 4, 'resolveAfter' => 30, 'rating' => 3, 'handler' => $this->akademik2, 'title' => 'Tiket B']);
        $this->ticket(['status' => TicketStatus::Ditolak, 'created' => '2026-10-07 08:00', 'claimAfter' => 6, 'resolveAfter' => 8, 'title' => 'Tiket C']);
        $this->ticket(['status' => TicketStatus::Diproses, 'created' => '2026-10-19 20:00', 'claimAfter' => 12, 'title' => 'Tiket D']);
        $this->ticket(['status' => TicketStatus::Menunggu, 'created' => '2026-10-18 10:00', 'title' => 'Tiket E menganggur']);
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => '2026-09-10 08:00', 'claimAfter' => 1, 'resolveAfter' => 100, 'rating' => 1, 'title' => 'Tiket F September']);
        // Tiket FEBI tidak boleh ikut terhitung di FITK.
        $this->ticketFor($this->mahasiswa, $this->cat('KRS belum di-ACC', $this->febi), ['created_at' => Carbon::parse('2026-10-08 08:00')]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_summary_follows_prd_definitions(): void
    {
        $metrics = new TicketMetrics;
        $kpi = $metrics->summarize($metrics->rows(Ticket::where('tickets.faculty_id', $this->fitk->id)->where('tickets.created_at', '>=', '2026-10-01')));

        $this->assertSame(5, $kpi['total']);
        $this->assertSame(['menunggu' => 1, 'diproses' => 1, 'selesai' => 2, 'ditolak' => 1], array_intersect_key($kpi, array_flip(['menunggu', 'diproses', 'selesai', 'ditolak'])));
        $this->assertSame(80.0, $kpi['claim_rate']);
        $this->assertEquals(6 * 3600, $kpi['avg_claim_seconds']);
        $this->assertSame(66.7, $kpi['completion_rate']);
        $this->assertSame(50.0, $kpi['sla_rate']);
        $this->assertSame(1, $kpi['sla_met']);
        $this->assertEquals(25 * 3600, $kpi['avg_resolution_seconds']);
        $this->assertSame(4.0, $kpi['csat']);
        $this->assertSame(2, $kpi['rated']);
    }

    public function test_empty_data_gives_null_not_zero_percent(): void
    {
        $kpi = (new TicketMetrics)->summarize([]);

        $this->assertSame(0, $kpi['total']);
        foreach (['claim_rate', 'avg_claim_seconds', 'completion_rate', 'sla_rate', 'avg_resolution_seconds', 'csat'] as $key) {
            $this->assertNull($kpi[$key], $key);
        }
    }

    public function test_dashboard_shows_real_cards_and_kpis(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();

        $response->assertViewHas('cards', [
            'masuk_bulan_ini' => 5,
            'menunggu' => 1,
            'diproses' => 1,
            'selesai_hari_ini' => 0,
            'ditolak_bulan_ini' => 1,
        ]);
        $response->assertViewHas('kpi', fn (array $kpi) => $kpi['sla_rate'] === 50.0 && $kpi['claim_rate'] === 80.0 && $kpi['csat'] === 4.0);
        $response->assertSee('50%')->assertSee('80%')->assertSee('66,7%')->assertSee('4,0 / 5')->assertSee('6 jam');

        // Tiket menganggur: Menunggu + lewat SLA, dengan tombol Ping.
        $response->assertViewHas('idleTotal', 1)->assertSee('Tiket E menganggur')->assertSee('Ping / Ingatkan');

        // Performa staff bulan ini: Sari (A, C, D) dan Rudi (B).
        $response->assertViewHas('staffPerformance', function (array $rows) {
            $sari = collect($rows)->first(fn ($row) => $row['staff']->is($this->akademik));
            $rudi = collect($rows)->first(fn ($row) => $row['staff']->is($this->akademik2));

            return $sari['handled'] === 3 && $sari['selesai'] === 1 && $sari['sla_rate'] === 100.0
                && $rudi['handled'] === 1 && $rudi['sla_rate'] === 0.0
                && collect($rows)->doesntContain(fn ($row) => $row['staff']->is($this->nonaktif)); // nonaktif tanpa tiket tidak tampil
        });

        // Coverage "Kelompok tanpa Staff" tetap ada.
        $response->assertSee('Cakupan spesialisasi');
    }

    public function test_seven_day_trend_counts_created_and_resolved_per_day(): void
    {
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => '2026-10-19 08:00', 'claimAfter' => 1, 'resolveAfter' => 3]);

        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertViewHas('trend', function (array $trend) {
                $masuk = array_column($trend['masuk'], 'value', 'key');
                $selesai = array_column($trend['selesai'], 'value', 'key');

                return count($trend['masuk']) === 7
                    && $masuk['2026-10-18'] === 1 && $masuk['2026-10-19'] === 2 && $masuk['2026-10-14'] === 0
                    && $selesai['2026-10-19'] === 1 && array_sum($selesai) === 1;
            });
    }

    public function test_series_by_week_and_month(): void
    {
        $metrics = new TicketMetrics;
        $rows = $metrics->rows(Ticket::where('tickets.faculty_id', $this->fitk->id));

        $months = $metrics->series($rows, Carbon::parse('2026-09-01'), Carbon::parse('2026-10-31'), 'month');
        $this->assertSame(['2026-09-01' => 1, '2026-10-01' => 5], array_column($months, 'value', 'key'));

        $weeks = $metrics->series($rows, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-20'), 'week');
        $this->assertSame('2026-09-28', $weeks[0]['key']); // minggu dimulai Senin
        $this->assertSame(5, array_sum(array_column($weeks, 'value')));
    }

    public function test_distribution_per_group_and_reporter_type(): void
    {
        $this->ticketFor($this->mahasiswa, $this->cat('Fasilitas, Sarana & Prasarana'), ['created_at' => Carbon::parse('2026-10-10 09:00')]);

        $metrics = new TicketMetrics;
        $rows = $metrics->rows(Ticket::where('tickets.faculty_id', $this->fitk->id)->where('tickets.created_at', '>=', '2026-10-01'));
        $groups = $metrics->byGroup($rows, $this->fitk->id);

        $this->assertSame('Sistem Akademik & Perkuliahan', $groups[0]['group']->name);
        $this->assertSame(5, $groups[0]['total']);
        $this->assertSame([['KRS belum di-ACC', 5]], array_map(fn ($c) => [$c['category']->name, $c['total']], $groups[0]['children']));
        $this->assertSame(['Fasilitas, Sarana & Prasarana', 1, []], [$groups[1]['group']->name, $groups[1]['total'], $groups[1]['children']]);

        $types = collect($metrics->byReporterType($rows))->mapWithKeys(fn ($r) => [$r['type']->value => $r['total']])->all();
        $this->assertSame(['mahasiswa' => 6, 'dosen' => 0, 'alumni' => 0], $types);
    }
}
