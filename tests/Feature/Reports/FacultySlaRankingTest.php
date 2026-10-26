<?php

namespace Tests\Feature\Reports;

use App\Enums\TicketStatus;
use App\Models\Faculty;
use App\Models\User;
use App\Services\Reports\TicketMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\AdminMonitoring\AdminTestCase;

/**
 * Peringkat Performa SLA Fakultas di dashboard pelapor (US-02 poin 5–8, F-TKU-08).
 *
 * Semua tiket di sini memakai kategori "KRS belum di-ACC" (target 24 jam), kecuali disebut lain,
 * jadi rasio = jam penyelesaian ÷ 24.
 */
class FacultySlaRankingTest extends AdminTestCase
{
    private Faculty $fua;

    /** Pemilik tiket-tiket Selesai (bukan $mahasiswa, supaya daftar "Tiket Terbaru" di dashboard tetap kosong). */
    private User $otherReporter;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 12:00');
        $this->fua = Faculty::factory()->create(['code' => 'FUA', 'name' => 'Fakultas Ushuluddin dan Adab']);
        $this->otherReporter = User::factory()->mahasiswa($this->fitk)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function resolved(Faculty $faculty, float $hours, TicketStatus $status = TicketStatus::Selesai, string $category = 'KRS belum di-ACC'): void
    {
        $this->ticket([
            'category' => $this->cat($category, $faculty),
            'status' => $status,
            'created' => '2026-10-01 08:00',
            'resolveAfter' => $hours,
            'handler' => $this->akademik,
            'reporter' => $this->otherReporter,
        ]);
    }

    private function ranking(): array
    {
        return app(TicketMetrics::class)->facultyRanking();
    }

    public function test_faculties_are_ordered_from_fast_to_slow_with_colors(): void
    {
        $this->resolved($this->febi, 30);   // rasio 1,25 -> merah
        $this->resolved($this->fitk, 6);    // rasio 0,25
        $this->resolved($this->fitk, 12);   // rasio 0,5  -> rata-rata 0,38 hijau
        $this->resolved($this->fua, 20);    // rasio 0,83 -> kuning

        $ranking = $this->ranking();

        $this->assertSame(['FITK', 'FUA', 'FEBI'], array_column($ranking, 'code'));
        $this->assertSame([1, 2, 3], array_column($ranking, 'rank'));
        $this->assertSame(['good', 'warning', 'bad'], array_column($ranking, 'tone'));
        $this->assertSame(0.38, $ranking[0]['avg_ratio']);
        $this->assertSame(9.0 * 3600, $ranking[0]['avg_resolution_seconds']);
        $this->assertSame(100.0, $ranking[0]['sla_rate']);
        $this->assertSame(0.0, $ranking[2]['sla_rate']);
    }

    public function test_rejected_and_open_tickets_are_not_counted(): void
    {
        $this->resolved($this->fitk, 6);
        $this->resolved($this->fitk, 200, TicketStatus::Ditolak);   // lama sekali, tapi Ditolak -> diabaikan
        $this->ticket(['status' => TicketStatus::Diproses]);          // belum selesai -> diabaikan

        $fitk = collect($this->ranking())->firstWhere('code', 'FITK');

        $this->assertSame(1, $fitk['resolved']);
        $this->assertSame(0.25, $fitk['avg_ratio']);
        $this->assertSame('good', $fitk['tone']);
    }

    public function test_ratio_uses_the_sla_snapshot_of_each_ticket(): void
    {
        // Target kategori diubah SETELAH tiket dibuat: penilaian tetap memakai snapshot 24 jam.
        $this->resolved($this->fitk, 30);
        $this->cat('KRS belum di-ACC')->update(['sla_hours' => 72]);

        $this->assertSame('bad', collect($this->ranking())->firstWhere('code', 'FITK')['tone']);
    }

    public function test_only_active_faculties_and_no_data_goes_last_without_rank(): void
    {
        $this->resolved($this->fitk, 6);
        $this->resolved($this->fua, 6);
        $this->fua->update(['is_active' => false]);

        $ranking = $this->ranking();

        $this->assertSame(['FITK', 'FEBI'], array_column($ranking, 'code'));
        $this->assertNull($ranking[1]['rank']);          // FEBI belum punya tiket Selesai
        $this->assertSame('neutral', $ranking[1]['tone']);
    }

    public function test_dashboard_shows_only_faculty_aggregates_without_sensitive_category(): void
    {
        $this->resolved($this->fitk, 30, TicketStatus::Selesai, 'Integritas Dosen'); // target 120 jam -> rasio 0,25
        $this->resolved($this->febi, 23);

        $this->actingAs($this->mahasiswa)->get('/dashboard')
            ->assertOk()
            ->assertSee('Performa SLA Fakultas')
            ->assertSeeInOrder(['FITK', 'Cepat', 'FEBI', 'Sesuai target'])
            ->assertSee('100%')
            ->assertDontSee('Integritas Dosen');
    }

    public function test_ranking_is_cached_briefly(): void
    {
        $this->actingAs($this->mahasiswa)->get('/dashboard')->assertSee('Belum ada tiket selesai');
        $this->assertTrue(Cache::has(TicketMetrics::RANKING_CACHE_KEY));

        $this->resolved($this->fitk, 6);
        $this->actingAs($this->mahasiswa)->get('/dashboard')->assertSee('Belum ada tiket selesai'); // masih dari cache

        Carbon::setTestNow(now()->addSeconds(TicketMetrics::RANKING_CACHE_SECONDS + 1));
        $this->actingAs($this->mahasiswa)->get('/dashboard')->assertDontSee('Belum ada tiket selesai');
    }
}
