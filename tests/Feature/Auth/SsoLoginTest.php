<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Enums\UserType;
use App\Models\Faculty;
use App\Models\User;
use App\Services\Auth\LoginService;
use Database\Seeders\FacultySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Login SSO dengan driver 'fake' (config/sso.php -> fake_users).
 * Field login TANPA "@" -> SSO.
 */
class SsoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.driver' => 'fake']);
        $this->seed(FacultySeeder::class); // fixture SSO memakai kode fakultas FITK & FEBI
    }

    public function test_first_sso_login_creates_a_mahasiswa_account(): void
    {
        $this->post('/login', ['login' => '2210110001', 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $user = User::where('nim', '2210110001')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(Role::EndUser, $user->role);
        $this->assertSame(UserType::Mahasiswa, $user->user_type);
        $this->assertNull($user->graduation_year);
        $this->assertSame('Ahmad Mahasiswa', $user->name);
        $this->assertSame('mahasiswa@uinssc.test', $user->email);
        $this->assertSame('Pendidikan Agama Islam', $user->program_studi);
        $this->assertSame(Faculty::where('code', 'FITK')->value('id'), $user->origin_faculty_id);
        $this->assertNull($user->faculty_id);
        $this->assertTrue($user->is_active);
    }

    public function test_sso_password_is_not_stored(): void
    {
        $this->post('/login', ['login' => '2210110001', 'password' => 'password']);
        $this->post('/logout');

        // Akun SSO baru diberi password acak, jadi login LOKAL dengan password SSO tidak bisa.
        $this->post('/login', ['login' => 'mahasiswa@uinssc.test', 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_graduated_student_from_2022_onward_becomes_alumni(): void
    {
        $this->post('/login', ['login' => '1910110045', 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $user = User::where('nim', '1910110045')->firstOrFail();
        $this->assertSame(UserType::Alumni, $user->user_type);
        $this->assertSame(2023, $user->graduation_year);
    }

    public function test_existing_mahasiswa_is_synced_to_alumni_after_graduating(): void
    {
        // Dulu tercatat mahasiswa, sekarang SSO bilang sudah lulus 2023.
        $user = User::factory()->create([
            'nim' => '1910110045',
            'user_type' => UserType::Mahasiswa,
            'name' => 'Nama Lama',
        ]);

        $this->post('/login', ['login' => '1910110045', 'password' => 'password']);

        $user->refresh();
        $this->assertSame(UserType::Alumni, $user->user_type);
        $this->assertSame(2023, $user->graduation_year);
        $this->assertSame('Citra Alumni SSO', $user->name);
        $this->assertSame(1, User::where('nim', '1910110045')->count());
    }

    public function test_dosen_logs_in_with_nidn(): void
    {
        $this->post('/login', ['login' => '0412038801', 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $user = User::where('nidn', '0412038801')->firstOrFail();
        $this->assertSame(UserType::Dosen, $user->user_type);
        $this->assertNull($user->nim);
    }

    public function test_sso_login_keeps_role_and_faculty_of_an_appointed_admin(): void
    {
        // Dosen yang sudah ditunjuk Super Admin menjadi Admin Fakultas FUA.
        $fua = Faculty::where('code', 'FUA')->first();
        $admin = User::factory()->dosen()->create([
            'nidn' => '0412038801',
            'role' => Role::Admin,
            'faculty_id' => $fua->id,
        ]);

        $this->post('/login', ['login' => '0412038801', 'password' => 'password'])
            ->assertRedirect('/admin/dashboard');

        $admin->refresh();
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertSame($fua->id, $admin->faculty_id);
        $this->assertSame(UserType::Dosen, $admin->user_type);
        // Fakultas ASAL tetap disinkronkan dari SSO (FITK), terpisah dari fakultas penugasan.
        $this->assertSame(Faculty::where('code', 'FITK')->value('id'), $admin->origin_faculty_id);
    }

    public function test_missing_email_uses_fallback_domain(): void
    {
        config(['sso.email_fallback_domain' => 'sso.contoh.ac.id']);

        $this->post('/login', ['login' => '2310210007', 'password' => 'password']);

        $user = User::where('nim', '2310210007')->firstOrFail();
        $this->assertSame('2310210007@sso.contoh.ac.id', $user->email);
        $this->assertSame(Faculty::where('code', 'FEBI')->value('id'), $user->origin_faculty_id);
    }

    public function test_email_already_used_by_another_account_falls_back(): void
    {
        User::factory()->staff()->create(['email' => 'mahasiswa@uinssc.test']);
        config(['sso.email_fallback_domain' => 'sso.contoh.ac.id']);

        $this->post('/login', ['login' => '2210110001', 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertSame('2210110001@sso.contoh.ac.id', User::where('nim', '2210110001')->value('email'));
    }

    public function test_wrong_sso_password_is_rejected(): void
    {
        $this->post('/login', ['login' => '2210110001', 'password' => 'salah'])
            ->assertSessionHasErrors(['login' => LoginService::IDENTITY_FAILED_MESSAGE]);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['nim' => '2210110001']);
    }

    public function test_alumni_before_2022_is_not_in_sso_but_can_log_in_with_nim_locally(): void
    {
        // Dedi (lulus 2019) ada di database tapi TIDAK ada di SSO.
        // Dulu test ini justru mengharapkan error (itulah bug M1). Sekarang NIM-nya dicek ke database lokal.
        $dedi = User::factory()->alumni(2019)->create(['nim' => '1510110023']);

        $this->post('/login', ['login' => '1510110023', 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($dedi);
        $this->assertFalse(session()->has('sso_token')); // tidak lewat SSO
    }

    public function test_inactive_sso_account_is_rejected(): void
    {
        User::factory()->inactive()->create(['nim' => '2210110001']);

        $this->post('/login', ['login' => '2210110001', 'password' => 'password'])
            ->assertSessionHasErrors(['login' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi Admin Fakultas.']);

        $this->assertGuest();
    }

    public function test_unreachable_bridge_is_shown_on_login_form(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn () => throw new ConnectionException('timeout'));
        config(['sso.driver' => 'bridge', 'sso.bridge_url' => 'https://bridge.test']);

        $this->post('/login', ['login' => '2210110001', 'password' => 'x'])
            ->assertSessionHasErrors(['login' => 'Layanan SSO kampus sedang tidak dapat dihubungi. Silakan coba beberapa saat lagi.']);

        $this->assertGuest();
    }

    public function test_sso_token_is_kept_in_session_for_logout(): void
    {
        $this->post('/login', ['login' => '2210110001', 'password' => 'password'])
            ->assertSessionHas('sso_token');
    }
}
