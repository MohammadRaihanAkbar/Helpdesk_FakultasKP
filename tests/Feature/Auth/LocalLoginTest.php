<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login lokal (field berisi "@"): Staff, Admin, Super Admin, Alumni <= 2021.
 */
class LocalLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_login_with_email_and_is_redirected_to_staff_dashboard(): void
    {
        $staff = User::factory()->staff()->create(['email' => 'staff@uinssc.test']);

        $this->post('/login', ['login' => 'staff@uinssc.test', 'password' => 'password'])
            ->assertRedirect('/staff/dashboard');

        $this->assertAuthenticatedAs($staff);
    }

    public function test_each_role_is_redirected_to_its_own_dashboard(): void
    {
        $cases = [
            '/dashboard' => User::factory()->alumni(2019)->create(),
            '/admin/dashboard' => User::factory()->admin()->create(),
            '/super-admin/dashboard' => User::factory()->superAdmin()->create(),
        ];

        foreach ($cases as $expectedUrl => $user) {
            $this->post('/login', ['login' => $user->email, 'password' => 'password'])
                ->assertRedirect($expectedUrl);

            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
        }
    }

    public function test_email_is_case_insensitive_and_trimmed(): void
    {
        $user = User::factory()->staff()->create(['email' => 'staff@uinssc.test']);

        $this->post('/login', ['login' => '  Staff@UINSSC.test ', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->staff()->create();

        $this->from('/login')
            ->post('/login', ['login' => $user->email, 'password' => 'salah'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['login' => 'Email atau password salah.']);

        $this->assertGuest();
    }

    public function test_validation_messages_are_in_indonesian(): void
    {
        $this->post('/login', ['login' => '', 'password' => ''])
            ->assertSessionHasErrors([
                'login' => 'NIM / NIP / Email wajib diisi.',
                'password' => 'Password wajib diisi.',
            ]);
    }

    public function test_inactive_account_is_rejected(): void
    {
        $user = User::factory()->staff()->inactive()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['login' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi Admin Fakultas.']);

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $user = User::factory()->staff()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => $user->email, 'password' => 'salah']);
        }

        // Percobaan ke-6, walau password benar, tetap ditolak sementara.
        $response = $this->post('/login', ['login' => $user->email, 'password' => 'password']);

        $response->assertSessionHasErrors('login');
        $this->assertStringContainsString('Terlalu banyak percobaan login', session('errors')->first('login'));
        $this->assertGuest();
    }

    public function test_remember_me_cookie_lasts_seven_days(): void
    {
        $user = User::factory()->staff()->create();

        $response = $this->post('/login', ['login' => $user->email, 'password' => 'password', 'remember' => '1']);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNotNull($cookie, 'Cookie remember me tidak dibuat.');
        // 10080 menit = 7 hari (toleransi 1 menit)
        $this->assertEqualsWithDelta(now()->addMinutes(10080)->getTimestamp(), $cookie->getExpiresTime(), 60);
    }

    public function test_seeded_demo_accounts_can_log_in(): void
    {
        $this->seed(); // DatabaseSeeder: akun demo dari UserSeeder

        $this->post('/login', ['login' => 'superadmin@uinssc.test', 'password' => 'password'])
            ->assertRedirect('/super-admin/dashboard');
        $this->post('/logout');

        $this->post('/login', ['login' => 'admin.fitk@uinssc.test', 'password' => 'password'])
            ->assertRedirect('/admin/dashboard');
        $this->post('/logout');

        $this->post('/login', ['login' => 'dedi.alumni2019@example.com', 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->post('/logout');

        // Akun SSO dari seeder dipakai ulang (dicari lewat NIM), tidak dibuat dobel.
        $this->post('/login', ['login' => '2210110001', 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->assertSame(1, User::where('nim', '2210110001')->count());
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}
