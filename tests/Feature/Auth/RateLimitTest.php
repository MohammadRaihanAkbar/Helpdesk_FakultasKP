<?php

namespace Tests\Feature\Auth;

use App\Models\Faculty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batas request per IP (lapisan kedua, AppServiceProvider::configureRateLimiting()).
 *
 * Bug yang ditemukan saat uji jalan: dulu route memakai 'throttle:20,1' dan 'throttle:6,1'.
 * Throttle tanpa nama menghitung per IP untuk semua route, sehingga setelah 6 kali login
 * dari satu IP, form registrasi alumni ikut ditolak 429.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.alumni_registration_key' => null]);
    }

    private function registrationData(int $i, int $facultyId): array
    {
        return [
            'name' => "Alumni {$i}",
            'email' => "alumni{$i}@example.com",
            'nim' => '15101100'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'graduation_year' => 2019,
            'origin_faculty_id' => $facultyId,
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ];
    }

    public function test_many_logins_do_not_block_alumni_registration(): void
    {
        $faculty = Faculty::factory()->create();
        $staff = User::factory()->staff($faculty)->create();

        // 10 kali login-logout berhasil dari IP yang sama (mis. satu lab komputer)
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['login' => $staff->email, 'password' => 'password'])->assertRedirect();
            $this->post('/logout');
        }

        $this->post('/register/alumni', $this->registrationData(1, $faculty->id))
            ->assertRedirect(route('register.alumni.done'))
            ->assertSessionHasNoErrors();
    }

    public function test_alumni_registration_is_limited_to_six_per_minute_per_ip(): void
    {
        $faculty = Faculty::factory()->create();

        for ($i = 1; $i <= 6; $i++) {
            $this->post('/register/alumni', $this->registrationData($i, $faculty->id))->assertRedirect(route('register.alumni.done'));
        }

        $this->post('/register/alumni', $this->registrationData(7, $faculty->id))
            ->assertStatus(429)
            ->assertSee('Terlalu banyak percobaan');
    }

    public function test_login_allows_many_users_from_one_campus_ip(): void
    {
        $faculty = Faculty::factory()->create();
        $users = User::factory()->staff($faculty)->count(25)->create();

        // 25 orang berbeda login dari IP yang sama dalam satu menit -> tidak boleh kena 429
        foreach ($users as $user) {
            $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertRedirect('/staff/dashboard');
            $this->post('/logout');
        }

        $this->assertGuest();
    }
}
