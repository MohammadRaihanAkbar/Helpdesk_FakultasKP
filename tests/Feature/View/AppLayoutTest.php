<?php

namespace Tests\Feature\View;

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Layout aplikasi (menu per peran, menu user, logout) lewat dashboard pelapor,
 * plus komponen x-ui.badge, x-ui.card, x-ui.empty-state.
 */
class AppLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_user_dashboard_uses_the_app_layout(): void
    {
        $user = User::factory()->create(['name' => 'Ahmad Mahasiswa']);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSeeText('Selamat datang, Ahmad')
            ->assertSee('Langsung ke konten')
            ->assertSee('aria-current="page"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('Belum ada tiket');
    }

    public function test_menu_only_shows_routes_that_exist(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        // Per M5 halaman "Fakultas" & "Pengguna" Super Admin sudah ada, jadi menunya tampil lengkap (PRD 7.5).
        $this->assertSame(['Dashboard', 'Fakultas', 'Pengguna'], array_column(Navigation::for($superAdmin), 'label'));

        // Item menu tetap disaring Route::has(): semua route menu memang terdaftar.
        foreach (Navigation::for($superAdmin) as $item) {
            $this->assertTrue(Route::has($item['route']));
        }
    }

    public function test_menu_differs_per_role(): void
    {
        foreach (['admin.queue', 'superadmin.users.index'] as $name) {
            Route::get('/_test/'.$name, fn () => 'ok')->name($name);
        }
        Route::getRoutes()->refreshNameLookups();

        $labels = fn (User $user) => array_column(Navigation::for($user), 'label');

        $this->assertSame(['Dashboard', 'Buat Tiket', 'Tiket Saya', 'Riwayat Tiket'], $labels(User::factory()->create()));
        $this->assertSame(['Dashboard', 'Antrean Tiket', 'Tiket Saya', 'Riwayat Tiket', 'Laporan Kerja'], $labels(User::factory()->staff()->create())); // M6: + Laporan Kerja
        $this->assertSame(['Dashboard', 'Monitor Antrean', 'Kelola Staff', 'Pengaturan Layanan', 'Laporan & Analitik', 'Verifikasi Alumni'], $labels(User::factory()->admin()->create())); // M6: + Verifikasi Alumni
        $this->assertSame(['Dashboard', 'Fakultas', 'Pengguna'], $labels(User::factory()->superAdmin()->create()));
        $this->assertSame(['Dashboard', 'Laporan & Ekspor'], $labels(User::factory()->dekan()->create())); // M6
    }

    public function test_my_tickets_menu_shows_number_of_active_tickets(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Ticket::factory()->for($category)->count(2)->create(['reporter_id' => $user->id]);
        Ticket::factory()->for($category)->status(TicketStatus::Selesai)->create(['reporter_id' => $user->id]);

        $menu = collect(Navigation::for($user))->keyBy('route');

        $this->assertSame(2, $menu['tickets.index']['count']);
        $this->assertNull($menu['dashboard']['count']);
    }

    public function test_badge_uses_ticket_status_label_and_color(): void
    {
        $this->blade('<x-ui.badge :status="$status" />', ['status' => TicketStatus::Ditolak])
            ->assertSee('Ditolak')
            ->assertSee('bg-ditolak-bg', false)
            ->assertSee('bg-ditolak-dot', false);
    }

    public function test_card_and_empty_state_render_title_and_action(): void
    {
        $this->blade(
            '<x-ui.card title="Tiket"><x-ui.empty-state title="Kosong">Belum ada data.<x-slot:action><a href="/x">Tambah</a></x-slot:action></x-ui.empty-state></x-ui.card>'
        )
            ->assertSeeInOrder(['Tiket', 'Kosong', 'Belum ada data.', 'Tambah']);
    }
}
