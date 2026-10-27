<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Enums\UserType;
use App\Models\Faculty;
use App\Models\User;
use App\Services\Auth\LoginService;
use Database\Seeders\FacultySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug M1: "alumni sudah registrasi & tersimpan di database, tapi saat login malah
 * 'NIM/NIDN atau password salah'".
 *
 * Penyebab: input tanpa "@" (NIM) selalu dikirim ke SSO, padahal alumni lulus <= 2021
 * tidak ada di SSO. Test di sini membuktikan alur lengkap REGISTRASI -> LOGIN sekarang lancar,
 * baik memakai NIM maupun email, dan tidak membuka celah untuk akun SSO.
 */
class AlumniLoginTest extends TestCase
{
    use RefreshDatabase;

    private Faculty $faculty;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.driver' => 'fake', 'auth.alumni_registration_key' => null]);
        $this->faculty = Faculty::factory()->create();
    }

    /** Daftar lewat hidden link persis seperti alumni mengisi form. */
    private function register(array $overrides = [], string $url = '/register/alumni'): void
    {
        $this->post($url, array_merge([
            'name' => 'Rina Alumni 2018',
            'email' => 'rina.alumni@example.com',
            'nim' => '1410110077',
            'graduation_year' => 2018,
            'origin_faculty_id' => $this->faculty->id,
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $overrides))->assertRedirect(route('register.alumni.done'))->assertSessionHasNoErrors();

        $this->assertGuest();

        // [M6] Akun baru menunggu verifikasi Admin Fakultas. Tes di file ini tentang LOGIN setelah akun aktif,
        // jadi akunnya langsung disetujui di sini (alur verifikasinya dites di Tests\Feature\Alumni).
        User::latest('id')->firstOrFail()->forceFill(['account_status' => AccountStatus::Approved])->save();
    }

    private function registeredUser(): User
    {
        return User::where('nim', '1410110077')->firstOrFail();
    }

    public function test_alumni_can_log_in_with_nim_right_after_registering(): void
    {
        $this->register();

        $this->post('/login', ['login' => '1410110077', 'password' => 'rahasia123'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($this->registeredUser());
        $this->get('/dashboard')->assertOk()->assertSee('Rina');
    }

    public function test_alumni_can_log_in_with_email_right_after_registering(): void
    {
        $this->register();

        $this->post('/login', ['login' => 'rina.alumni@example.com', 'password' => 'rahasia123'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($this->registeredUser());
    }

    public function test_email_typed_with_capital_letters_and_spaces_still_works(): void
    {
        // Saat daftar email ditulis dengan huruf besar -> disimpan huruf kecil.
        $this->register(['email' => '  Rina.Alumni@Example.COM ']);
        $this->assertSame('rina.alumni@example.com', $this->registeredUser()->email);

        $this->post('/login', ['login' => ' RINA.ALUMNI@example.com ', 'password' => 'rahasia123'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_nim_with_spaces_from_copy_paste_still_works(): void
    {
        $this->register(['nim' => ' 1410110077 ']);

        $this->post('/login', ['login' => ' 1410110077 ', 'password' => 'rahasia123'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_password_is_hashed_exactly_once(): void
    {
        $this->register();
        $hash = $this->registeredUser()->password;

        $this->assertNotSame('rahasia123', $hash);
        $this->assertTrue(Hash::check('rahasia123', $hash)); // hash ganda akan membuat ini false
        $this->assertSame('bcrypt', Hash::info($hash)['algoName']);
    }

    public function test_registration_and_login_work_with_the_hidden_link_key(): void
    {
        config(['auth.alumni_registration_key' => 'kunci-rahasia']);

        $this->register([], '/register/alumni?key=kunci-rahasia');

        $this->post('/login', ['login' => '1410110077', 'password' => 'rahasia123'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_wrong_password_with_nim_shows_a_clear_message(): void
    {
        $this->register();

        $this->post('/login', ['login' => '1410110077', 'password' => 'salah-total'])
            ->assertSessionHasErrors(['login' => LoginService::IDENTITY_FAILED_MESSAGE]);

        $this->assertGuest();
    }

    public function test_inactive_alumni_cannot_log_in_with_nim(): void
    {
        $this->register();
        $this->registeredUser()->update(['is_active' => false]);

        $this->post('/login', ['login' => '1410110077', 'password' => 'rahasia123'])
            ->assertSessionHasErrors(['login' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi Admin Fakultas.']);

        $this->assertGuest();
    }

    public function test_alumni_nim_login_does_not_need_the_campus_sso(): void
    {
        // Bridge kampus sedang mati: alumni hidden link tetap bisa masuk karena dicek di database lokal.
        config(['sso.driver' => 'bridge', 'sso.bridge_url' => 'https://bridge.test']);
        Http::preventStrayRequests();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        User::factory()->alumni(2019)->create(['nim' => '1510110023']);

        $this->post('/login', ['login' => '1510110023', 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        Http::assertNothingSent();
    }

    public function test_alumni_from_2022_onward_cannot_bypass_sso_with_a_local_password(): void
    {
        // Akun alumni >= 2022 (hasil sinkron SSO). Walau password lokalnya diketahui,
        // login dengan NIM tetap harus lewat SSO. NIM ini tidak ada di SSO -> ditolak.
        User::factory()->alumni(2023)->create(['nim' => '1999000001']);

        $this->post('/login', ['login' => '1999000001', 'password' => 'password'])
            ->assertSessionHasErrors(['login' => LoginService::IDENTITY_FAILED_MESSAGE]);

        $this->assertGuest();
    }

    public function test_mahasiswa_account_cannot_bypass_sso_with_a_local_password(): void
    {
        User::factory()->mahasiswa()->create(['nim' => '2299000001']);

        $this->post('/login', ['login' => '2299000001', 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_dosen_account_cannot_bypass_sso_with_a_local_password(): void
    {
        User::factory()->dosen()->create(['nidn' => '0499000001']);

        $this->post('/login', ['login' => '0499000001', 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_hidden_link_registrant_loses_access_once_the_real_sso_owner_logs_in(): void
    {
        $this->seed(FacultySeeder::class);

        // Seseorang mendaftar lewat hidden link memakai NIM mahasiswa aktif (yang belum pernah login).
        $this->register(['nim' => '2210110001', 'email' => 'penyusup@example.com']);
        $this->post('/login', ['login' => 'penyusup@example.com', 'password' => 'rahasia123'])->assertRedirect('/dashboard');
        $this->post('/logout');

        // Pemilik NIM yang sah login lewat SSO -> akun disinkronkan dari SSO.
        $this->post('/login', ['login' => '2210110001', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->post('/logout');

        $user = User::where('nim', '2210110001')->firstOrFail();
        $this->assertSame(UserType::Mahasiswa, $user->user_type);
        $this->assertFalse(Hash::check('rahasia123', $user->password)); // password hidden link dibuang

        // Pendaftar hidden link tidak bisa masuk lagi, lewat jalur mana pun.
        foreach (['2210110001', 'penyusup@example.com', 'mahasiswa@uinssc.test'] as $login) {
            $this->post('/login', ['login' => $login, 'password' => 'rahasia123'])->assertSessionHasErrors('login');
            $this->assertGuest();
        }
    }

    public function test_seeded_demo_alumni_can_log_in_with_nim_and_email(): void
    {
        $this->seed();

        foreach (['1510110023', 'dedi.alumni2019@example.com'] as $login) {
            $this->post('/login', ['login' => $login, 'password' => 'password'])
                ->assertRedirect('/dashboard');

            $user = auth()->user();
            $this->assertSame(UserType::Alumni, $user->user_type);
            $this->assertSame('1510110023', $user->nim);

            $this->post('/logout');
        }
    }
}
