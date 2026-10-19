<?php

namespace Tests\Feature\Auth;

use App\Enums\UserType;
use App\Models\User;
use App\Services\Auth\LoginService;
use Database\Seeders\FacultySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * LoginService diuji langsung (tanpa route/form): memilih jalur lokal atau SSO,
 * menolak akun nonaktif, lalu memasukkan user ke session.
 */
class LoginServiceTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(string $login, string $password = 'password', bool $remember = false): User
    {
        return app(LoginService::class)->attempt($login, $password, $remember);
    }

    /** Jalankan percobaan login dan kembalikan pesan error untuk field "login". */
    private function errorFor(string $login, string $password = 'password'): string
    {
        try {
            $this->attempt($login, $password);
        } catch (ValidationException $e) {
            return $e->errors()['login'][0];
        }

        $this->fail('Seharusnya login ditolak');
    }

    public function test_login_containing_at_sign_is_local(): void
    {
        $this->assertTrue(LoginService::isLocalLogin('staff@uinssc.test'));
        $this->assertFalse(LoginService::isLocalLogin('2210110001'));
    }

    public function test_local_login_authenticates_the_user(): void
    {
        $staff = User::factory()->staff()->create(['email' => 'staff@uinssc.test']);

        $user = $this->attempt('staff@uinssc.test');

        $this->assertTrue($user->is($staff));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_local_login_with_wrong_password_is_rejected(): void
    {
        $staff = User::factory()->staff()->create();

        $this->assertSame('Email atau password salah.', $this->errorFor($staff->email, 'salah'));
        $this->assertGuest();
    }

    public function test_inactive_account_is_rejected(): void
    {
        $staff = User::factory()->staff()->inactive()->create();

        $this->assertSame('Akun Anda sedang dinonaktifkan. Silakan hubungi Admin Fakultas.', $this->errorFor($staff->email));
        $this->assertGuest();
    }

    public function test_sso_login_creates_the_user_and_keeps_the_token(): void
    {
        config(['sso.driver' => 'fake']);
        $this->seed(FacultySeeder::class);

        $user = $this->attempt('1910110045');

        $this->assertSame(UserType::Alumni, $user->user_type);
        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty(session(LoginService::SSO_TOKEN_SESSION_KEY));
    }

    public function test_sso_error_becomes_a_validation_message(): void
    {
        config(['sso.driver' => 'fake']);

        $this->assertSame(LoginService::IDENTITY_FAILED_MESSAGE, $this->errorFor('2210110001', 'salah'));
    }
}
