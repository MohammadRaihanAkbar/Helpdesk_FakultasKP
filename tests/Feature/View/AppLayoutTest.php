<?php

namespace Tests\Feature\View;

use App\Enums\TicketStatus;
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
        $user = User::factory()->create();

        // Di M1 baru Dashboard yang punya route.
        $this->assertSame(['Dashboard'], array_column(Navigation::for($user), 'label'));

        // Begitu route "Tiket Saya" dibuat (M2), menunya muncul sendiri.
        Route::get('/tickets', fn () => 'ok')->name('tickets.index');
        Route::getRoutes()->refreshNameLookups();

        $this->assertSame(['Dashboard', 'Tiket Saya'], array_column(Navigation::for($user), 'label'));
    }

    public function test_menu_differs_per_role(): void
    {
        foreach (['tickets.index', 'staff.queue', 'admin.queue', 'superadmin.users.index'] as $name) {
            Route::get('/_test/'.$name, fn () => 'ok')->name($name);
        }
        Route::getRoutes()->refreshNameLookups();

        $labels = fn (User $user) => array_column(Navigation::for($user), 'label');

        $this->assertSame(['Dashboard', 'Tiket Saya'], $labels(User::factory()->create()));
        $this->assertSame(['Dashboard', 'Antrean Tiket'], $labels(User::factory()->staff()->create()));
        $this->assertSame(['Dashboard', 'Monitor Antrean', 'Kelola Staff', 'Pengaturan Layanan'], $labels(User::factory()->admin()->create()));
        $this->assertSame(['Dashboard', 'Pengguna'], $labels(User::factory()->superAdmin()->create()));
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
