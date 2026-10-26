<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\TicketStatus;
use Illuminate\Support\Carbon;

/**
 * Dashboard Metrik Global (F-DSB-04, F-SPA-03, PRD 10.9): angka se-universitas + celah coverage per fakultas.
 */
class SuperAdminDashboardTest extends SuperAdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 12:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_super_admin_can_open_the_dashboard(): void
    {
        foreach ($this->nonSuperAdmins() as $user) {
            $this->actingAs($user)->get('/super-admin/dashboard')->assertForbidden();
        }
    }

    public function test_global_metrics_cover_all_faculties(): void
    {
        $febiKrs = $this->cat('KRS belum di-ACC', $this->febi); // SLA 24 jam, sama dengan FITK

        // FITK: selesai tepat SLA (rating 5), selesai lewat SLA (rating 3), satu masih menunggu
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => '2026-10-10 08:00', 'resolveAfter' => 10, 'rating' => 5]);
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => '2026-10-11 08:00', 'resolveAfter' => 30, 'rating' => 3]);
        $this->ticket(['created' => '2026-10-19 08:00']);
        // FEBI: selesai tepat SLA + ditolak (tidak dihitung SLA)
        $this->ticket(['category' => $febiKrs, 'handler' => $this->staffFebi, 'status' => TicketStatus::Selesai, 'created' => '2026-09-01 08:00', 'resolveAfter' => 5]);
        $this->ticket(['category' => $febiKrs, 'handler' => $this->staffFebi, 'status' => TicketStatus::Ditolak, 'created' => '2026-09-02 08:00']);

        $response = $this->actingAs($this->superAdmin)->get('/super-admin/dashboard')->assertOk();

        $kpi = $response->viewData('kpi');
        $this->assertSame(5, $kpi['total']);
        $this->assertSame(3, $kpi['selesai']);
        $this->assertSame(66.7, $kpi['sla_rate']); // 2 dari 3 tiket Selesai tepat SLA
        $this->assertSame(4.0, $kpi['csat']);

        $this->assertSame(2, $response->viewData('cards')['faculties_active']);
        $this->assertSame(3, $response->viewData('cards')['masuk_bulan_ini']); // tiket FITK Oktober

        $perFaculty = $response->viewData('facultyStats')->keyBy(fn ($row) => $row['faculty']->code);
        $this->assertSame([3, 50.0], [$perFaculty['FITK']['kpi']['total'], $perFaculty['FITK']['kpi']['sla_rate']]);
        $this->assertSame([2, 100.0], [$perFaculty['FEBI']['kpi']['total'], $perFaculty['FEBI']['kpi']['sla_rate']]);

        $response->assertSee('66,7%')->assertSee('4,0 / 5');
    }

    public function test_dashboard_lists_faculties_with_staff_coverage_gaps(): void
    {
        $this->ticket(['category' => $this->cat('Keuangan & UKT')]); // menunggu di kelompok tanpa staff

        $response = $this->actingAs($this->superAdmin)->get('/super-admin/dashboard')
            ->assertOk()
            ->assertSee('Celah Coverage Staff')
            ->assertSee('6 kelompok tanpa Staff')
            ->assertSee('1 tiket menunggu')
            ->assertSee('Keuangan &amp; UKT', false);

        $this->assertSame(['FITK', 'FEBI'], $response->viewData('gaps')->map(fn ($gap) => $gap['faculty']->code)->all());

        // Fakultas nonaktif tidak masuk daftar celah.
        $this->febi->update(['is_active' => false]);
        $gaps = $this->actingAs($this->superAdmin)->get('/super-admin/dashboard')->viewData('gaps');
        $this->assertSame(['FITK'], $gaps->map(fn ($gap) => $gap['faculty']->code)->all());
    }

    public function test_empty_system_shows_dashes_instead_of_zero_percent(): void
    {
        $this->actingAs($this->superAdmin)->get('/super-admin/dashboard')
            ->assertOk()
            ->assertSee('belum ada tiket selesai')
            ->assertSee('belum ada rating');
    }
}
