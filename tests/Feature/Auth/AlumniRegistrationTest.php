<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Enums\UserType;
use App\Http\Controllers\Auth\AlumniRegistrationController;
use App\Http\Requests\Auth\AlumniRegistrationRequest;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registrasi alumni lulus <= 2021 lewat hidden link (US-01B).
 */
class AlumniRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Faculty $faculty;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.alumni_registration_key' => null]);
        $this->faculty = Faculty::factory()->create();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dedi Alumni Lama',
            'email' => 'dedi@example.com',
            'nim' => '1510110023',
            'graduation_year' => 2019,
            'origin_faculty_id' => $this->faculty->id,
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $overrides);
    }

    public function test_alumni_who_graduated_in_2021_or_earlier_can_register(): void
    {
        $this->post('/register/alumni', $this->validData(['graduation_year' => 2021]))
            ->assertRedirect('/login')
            ->assertSessionHas('status', AlumniRegistrationController::SUCCESS_MESSAGE);

        $user = User::where('email', 'dedi@example.com')->firstOrFail();
        $this->assertSame(Role::EndUser, $user->role);
        $this->assertSame(UserType::Alumni, $user->user_type);
        $this->assertSame(2021, $user->graduation_year);
        $this->assertSame($this->faculty->id, $user->origin_faculty_id);
        $this->assertTrue($user->is_active);
        $this->assertGuest(); // tidak otomatis login

        // Setelah mendaftar, bisa login dengan email + password
        $this->post('/login', ['login' => 'dedi@example.com', 'password' => 'rahasia123'])
            ->assertRedirect('/dashboard');
    }

    public function test_graduation_year_2022_or_later_is_redirected_to_sso(): void
    {
        $this->post('/register/alumni', $this->validData(['graduation_year' => 2022]))
            ->assertSessionHasErrors(['graduation_year' => AlumniRegistrationRequest::ALUMNI_USE_SSO_MESSAGE]);

        $this->assertDatabaseMissing('users', ['email' => 'dedi@example.com']);
    }

    public function test_nim_that_is_already_registered_is_rejected_with_sso_message(): void
    {
        User::factory()->create(['nim' => '1510110023']);

        $this->post('/register/alumni', $this->validData())
            ->assertSessionHasErrors(['nim' => AlumniRegistrationRequest::ALUMNI_USE_SSO_MESSAGE]);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'dedi@example.com']);

        $this->post('/register/alumni', $this->validData())
            ->assertSessionHasErrors(['email' => 'Email ini sudah terdaftar. Silakan login dengan email tersebut.']);
    }

    public function test_nim_format_and_required_fields_are_validated(): void
    {
        $this->post('/register/alumni', $this->validData(['nim' => '12ab', 'name' => '', 'password_confirmation' => 'beda']))
            ->assertSessionHasErrors([
                'nim' => 'NIM harus berupa angka 8 sampai 20 digit.',
                'name' => 'Nama lengkap wajib diisi.',
                'password' => 'Konfirmasi password tidak cocok.',
            ]);
    }

    public function test_inactive_faculty_cannot_be_chosen(): void
    {
        $this->faculty->update(['is_active' => false]);

        $this->post('/register/alumni', $this->validData())
            ->assertSessionHasErrors(['origin_faculty_id' => 'Fakultas asal yang dipilih tidak valid.']);
    }

    public function test_key_guard_returns_404_without_the_right_key(): void
    {
        config(['auth.alumni_registration_key' => 'kunci-rahasia']);

        $this->get('/register/alumni')->assertNotFound();
        $this->get('/register/alumni?key=salah')->assertNotFound();
        $this->post('/register/alumni', $this->validData())->assertNotFound();

        $this->post('/register/alumni?key=kunci-rahasia', $this->validData())->assertRedirect('/login');
        $this->assertDatabaseHas('users', ['email' => 'dedi@example.com']);
    }

    public function test_logged_in_user_cannot_open_registration_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/register/alumni')
            ->assertRedirect('/dashboard');
    }
}
