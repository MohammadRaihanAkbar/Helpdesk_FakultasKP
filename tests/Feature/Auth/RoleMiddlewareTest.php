<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Middleware `role` (EnsureUserHasRole), `guest`, dan EnsureUserIsActive lewat request HTTP sungguhan.
 *
 * Halaman yang BERHASIL dibuka diuji memakai route khusus test (/_test/...) yang hanya
 * mengembalikan teks, supaya test ini tidak bergantung pada tampilan (view) dashboard.
 */
class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route tambahan yang hanya ada selama test berjalan.
        Route::middleware(['web', 'auth', 'role:staff'])->get('/_test/khusus-staff', fn () => 'halaman staff');
        Route::middleware(['web', 'auth', 'role:admin,super_admin'])->get('/_test/khusus-pengelola', fn () => 'halaman pengelola');
    }

    public static function dashboardProvider(): array
    {
        return [
            'end user' => ['/dashboard'],
            'staff' => ['/staff/dashboard'],
            'admin' => ['/admin/dashboard'],
            'super admin' => ['/super-admin/dashboard'],
        ];
    }

    #[DataProvider('dashboardProvider')]
    public function test_guest_is_redirected_to_login(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    public function test_dashboard_of_another_role_is_forbidden(): void
    {
        $users = [
            '/dashboard' => User::factory()->create(),
            '/staff/dashboard' => User::factory()->staff()->create(),
            '/admin/dashboard' => User::factory()->admin()->create(),
            '/super-admin/dashboard' => User::factory()->superAdmin()->create(),
        ];

        foreach ($users as $ownUrl => $user) {
            foreach (array_keys($users) as $url) {
                if ($url !== $ownUrl) {
                    $this->actingAs($user)->get($url)->assertForbidden();
                }
            }
        }
    }

    public function test_user_with_the_right_role_passes(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get('/_test/khusus-staff')
            ->assertOk()
            ->assertSee('halaman staff');
    }

    public function test_role_middleware_accepts_one_of_several_roles(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/_test/khusus-pengelola')->assertOk();
        $this->actingAs(User::factory()->superAdmin()->create())->get('/_test/khusus-pengelola')->assertOk();
        $this->actingAs(User::factory()->staff()->create())->get('/_test/khusus-pengelola')->assertForbidden();
    }

    public function test_logged_in_user_visiting_login_is_sent_to_own_dashboard(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get('/login')
            ->assertRedirect('/staff/dashboard');
    }

    public function test_user_deactivated_during_session_is_logged_out(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/_test/khusus-staff')->assertOk();

        // Admin Fakultas menonaktifkan akun saat Staff masih login
        $staff->update(['is_active' => false]);

        $this->get('/_test/khusus-staff')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }
}
