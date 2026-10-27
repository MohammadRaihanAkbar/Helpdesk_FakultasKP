<?php

namespace Tests\Feature\View;

use App\Enums\TicketStatus;
use App\Models\User;
use App\Services\Reports\LandingStatistics;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\AdminMonitoring\AdminTestCase;

/**
 * [M6] Halaman depan publik (/): terbuka untuk tamu, statistik agregat & tren, tanpa data pribadi,
 * kelompok sensitif tidak dirinci, tombol sesuai status login, tanpa tautan registrasi alumni.
 */
class LandingPageTest extends AdminTestCase
{
    public function test_guest_sees_profile_steps_catalog_and_login_button(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Helpdesk Fakultas Terpadu')
            ->assertSee('UIN Siber Syekh Nurjati Cirebon')
            ->assertSee('Cara kerja')
            ->assertSee('Selesai &amp; beri rating', false)
            ->assertSee('Kelompok layanan')
            ->assertSee('Kesalahan Data PDDIKTI')
            ->assertSee('Tren tiket')
            ->assertSee('FITK')
            ->assertSee('helpdeskfakultas@gmail.com')
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('images/gedung-siber.webp', false)
            ->assertDontSee('Buka Dashboard')
            ->assertDontSee('register/alumni');
        $this->assertGuest();
    }

    public function test_logged_in_user_sees_the_dashboard_button_for_their_role(): void
    {
        foreach ([[$this->mahasiswa, 'dashboard'], [$this->akademik, 'staff.dashboard'], [$this->admin, 'admin.dashboard']] as [$user, $route]) {
            $this->actingAs($user)->get('/')->assertOk()
                ->assertSee('Buka Dashboard')
                ->assertSee('href="'.route($route).'"', false);
        }
    }

    public function test_statistics_and_trends_are_aggregated_without_personal_data(): void
    {
        // 30 hari terakhir: 3 tiket Sistem Akademik (KRS), 1 Fasilitas; 30 hari sebelumnya: 1 tiket Sistem Akademik.
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(2), 'resolveAfter' => 10, 'title' => 'Judul rahasia pelapor']);
        $this->ticket(['created' => now()->subDays(3)]);
        $this->ticket(['created' => now()->subDays(4)]);
        $this->ticket(['created' => now()->subDays(5), 'category' => $this->cat('Fasilitas, Sarana & Prasarana'), 'handler' => $this->umum]);
        $this->ticket(['status' => TicketStatus::Selesai, 'created' => now()->subDays(40), 'resolveAfter' => 30]);

        $stats = app(LandingStatistics::class)->compute();

        $this->assertSame(5, $stats['summary']['total']);
        $this->assertSame(40.0, $stats['summary']['resolved_rate']);
        $this->assertSame(50.0, $stats['summary']['sla_rate']);
        $this->assertSame(4, $stats['summary']['last_30_days']);
        $this->assertSame(4, array_sum(array_column($stats['volume'], 'value')));
        $this->assertCount(30, $stats['volume']);

        $this->assertSame(['name' => 'Sistem Akademik & Perkuliahan', 'count' => 3, 'previous' => 1, 'share' => 75.0, 'change' => 200], $stats['trending'][0]);
        $this->assertSame(['Fasilitas, Sarana & Prasarana', null], [$stats['trending'][1]['name'], $stats['trending'][1]['change']]);
        $this->assertSame(['name' => 'KRS belum di-ACC', 'group' => 'Sistem Akademik & Perkuliahan', 'count' => 3], $stats['issues'][0]);

        $this->get('/')->assertOk()
            ->assertSee('Sistem Akademik &amp; Perkuliahan', false)
            ->assertSee('▲ 200%')
            ->assertDontSee('Judul rahasia pelapor')
            ->assertDontSee('Ahmad Mahasiswa')
            ->assertDontSee('Sari Akademik')
            ->assertDontSee('TKT-');
    }

    public function test_sensitive_group_counts_in_totals_but_never_appears_in_trend_details(): void
    {
        $this->ticket(['created' => now()->subDays(1), 'category' => $this->cat('Integritas Dosen'), 'title' => 'Dosen X tidak hadir']);
        $this->ticket(['created' => now()->subDays(1), 'category' => $this->cat('Integritas Dosen')]);
        $this->ticket(['created' => now()->subDays(2)]);

        $stats = app(LandingStatistics::class)->compute();

        $this->assertSame(3, $stats['summary']['total']);
        $this->assertSame(['Sistem Akademik & Perkuliahan'], array_column($stats['trending'], 'name'));
        $this->assertNotContains('Integritas Dosen', array_column($stats['issues'], 'group'));

        // Di halaman, "Integritas Dosen" hanya muncul sebagai nama layanan di katalog, tidak di bagian Tren.
        $html = $this->get('/')->assertOk()->getContent();
        $trend = substr($html, strpos($html, 'id="tren"'));
        $this->assertStringNotContainsString('Integritas Dosen', $trend);
        $this->assertStringNotContainsString('Dosen X tidak hadir', $html);
    }

    public function test_statistics_are_cached_for_15_minutes(): void
    {
        $this->get('/')->assertOk();
        $this->assertTrue(Cache::has(LandingStatistics::CACHE_KEY));

        $this->ticket(['created' => now()->subHour()]);
        $this->assertSame(0, app(LandingStatistics::class)->get()['summary']['total']); // masih dari cache

        $this->travel(901)->seconds();
        $this->assertSame(1, app(LandingStatistics::class)->get()['summary']['total']);
    }

    public function test_empty_database_still_renders_nicely(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Belum ada tiket bulan ini')
            ->assertSee('Belum ada data tren');
    }

    public function test_landing_page_keeps_the_security_headers_without_external_sources(): void
    {
        $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("img-src 'self'", $csp);
        $this->assertStringNotContainsString('https://', $csp);
    }

    public function test_inactive_user_is_not_shown_a_dashboard_button(): void
    {
        $inactive = User::factory()->inactive()->create();

        $this->actingAs($inactive)->get('/')->assertRedirect('/login');
    }
}
