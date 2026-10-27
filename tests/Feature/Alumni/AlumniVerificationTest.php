<?php

namespace Tests\Feature\Alumni;

use App\Enums\AccountStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Notifications\AlumniRegisteredMail;
use App\Notifications\AlumniVerifiedMail;
use App\Services\Auth\AlumniVerificationService;
use App\Services\Auth\LoginService;
use Database\Seeders\FacultySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\AdminMonitoring\AdminTestCase;

/**
 * [M6] Akun alumni baru menunggu verifikasi Admin Fakultas asal: tidak bisa login sampai disetujui,
 * setujui / tolak (dengan alasan), notifikasi in-app + email, daftar ulang setelah ditolak, batas fakultas.
 */
class AlumniVerificationTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.alumni_registration_key' => null]);
        Notification::fake();
    }

    private function register(array $overrides = []): User
    {
        $this->post('/register/alumni', array_merge([
            'name' => 'Rina Alumni 2018', 'email' => 'rina@example.com', 'nim' => '1410110077', 'graduation_year' => 2018,
            'origin_faculty_id' => $this->fitk->id, 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ], $overrides))->assertSessionHasNoErrors()->assertRedirect(route('register.alumni.done'));

        return User::where('nim', $overrides['nim'] ?? '1410110077')->firstOrFail();
    }

    private function login(string $login = '1410110077')
    {
        return $this->post('/login', ['login' => $login, 'password' => 'rahasia123']);
    }

    public function test_new_alumni_is_pending_and_cannot_log_in(): void
    {
        $alumni = $this->register();

        $this->assertSame(AccountStatus::Pending, $alumni->account_status);
        foreach (['1410110077', 'rina@example.com'] as $login) {
            $this->login($login)->assertSessionHasErrors(['login' => 'Akun Anda masih menunggu verifikasi Admin Fakultas FITK. Anda akan menerima email setelah akun disetujui.']);
            $this->assertGuest();
        }

        // Password salah tetap memakai pesan umum (tidak membocorkan bahwa akunnya ada).
        $this->post('/login', ['login' => '1410110077', 'password' => 'salah-total'])
            ->assertSessionHasErrors(['login' => LoginService::IDENTITY_FAILED_MESSAGE]);
    }

    public function test_admin_of_origin_faculty_is_notified_in_app_and_by_email(): void
    {
        $alumni = $this->register();

        $notification = $this->admin->inAppNotifications()->firstOrFail();
        $this->assertStringStartsWith(AlumniVerificationService::REGISTERED_SUBJECT, $notification->subject);
        $this->assertNull($notification->ticket_id);
        Notification::assertSentTo($this->admin, AlumniRegisteredMail::class, fn (AlumniRegisteredMail $mail) => $mail->alumni->is($alumni));
        Notification::assertNotSentTo($this->adminFebi, AlumniRegisteredMail::class);
        $this->assertSame(0, $this->adminFebi->inAppNotifications()->count());

        // Klik notifikasi -> halaman verifikasi; menu menampilkan jumlah yang menunggu.
        $this->actingAs($this->admin)->patch("/notifications/{$notification->id}/read")->assertRedirect(route('admin.alumni.index'));
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertSee('Verifikasi Alumni')->assertSee('1 pendaftaran alumni');
    }

    public function test_verification_page_lists_pending_alumni_of_own_faculty_only(): void
    {
        $this->register();
        $this->register(['nim' => '1310220011', 'email' => 'budi.febi@example.com', 'name' => 'Budi Alumni FEBI', 'origin_faculty_id' => $this->febi->id]);

        $this->actingAs($this->admin)->get('/admin/verifikasi-alumni')->assertOk()
            ->assertSee('Rina Alumni 2018')->assertSee('1410110077')->assertSee('rina@example.com')->assertSee('2018')
            ->assertDontSee('Budi Alumni FEBI');

        foreach ([$this->akademik, $this->mahasiswa] as $other) {
            $this->actingAs($other)->get('/admin/verifikasi-alumni')->assertForbidden();
        }
    }

    public function test_approve_lets_the_alumni_log_in_and_sends_an_email(): void
    {
        $alumni = $this->register();

        $this->actingAs($this->admin)->patch("/admin/verifikasi-alumni/{$alumni->id}/setujui")->assertRedirect()->assertSessionHas('status');
        $alumni->refresh();

        $this->assertSame([AccountStatus::Approved, $this->admin->id], [$alumni->account_status, $alumni->verified_by]);
        $this->assertNotNull($alumni->verified_at);
        Notification::assertSentTo($alumni, AlumniVerifiedMail::class, fn (AlumniVerifiedMail $mail) => $mail->status === AccountStatus::Approved);

        auth()->logout();
        $this->login()->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($alumni);
    }

    public function test_reject_requires_a_reason_and_the_alumni_sees_it(): void
    {
        $alumni = $this->register();

        $this->actingAs($this->admin)->patch("/admin/verifikasi-alumni/{$alumni->id}/tolak", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame(AccountStatus::Pending, $alumni->fresh()->account_status);

        $this->actingAs($this->admin)->patch("/admin/verifikasi-alumni/{$alumni->id}/tolak", ['reason' => 'NIM tidak ada di data alumni FITK'])->assertRedirect();
        $alumni->refresh();
        $this->assertSame([AccountStatus::Rejected, 'NIM tidak ada di data alumni FITK'], [$alumni->account_status, $alumni->rejection_reason]);
        Notification::assertSentTo($alumni, AlumniVerifiedMail::class,
            fn (AlumniVerifiedMail $mail) => $mail->status === AccountStatus::Rejected && $mail->reason === 'NIM tidak ada di data alumni FITK'
                && $mail->registrationUrl === route('register.alumni'));

        auth()->logout();
        $this->login()->assertSessionHasErrors('login');
        $this->assertStringContainsString('ditolak Admin Fakultas FITK. Alasan: NIM tidak ada di data alumni FITK', session('errors')->first('login'));
        $this->assertGuest();

        // Sudah diverifikasi -> tidak bisa disetujui lagi dari halaman ini.
        $this->actingAs($this->admin)->patch("/admin/verifikasi-alumni/{$alumni->id}/setujui")->assertForbidden();
    }

    public function test_rejected_alumni_can_register_again_with_the_same_nim_and_email(): void
    {
        $first = $this->register();
        $this->actingAs($this->admin)->patch("/admin/verifikasi-alumni/{$first->id}/tolak", ['reason' => 'Tahun lulus salah']);
        auth()->logout();

        $again = $this->register(['graduation_year' => 2017, 'name' => 'Rina Alumni']);

        $this->assertTrue($again->is($first)); // baris yang sama dipakai ulang, tidak ada akun ganda
        $this->assertSame([AccountStatus::Pending, 2017, null, null], [$again->account_status, $again->graduation_year, $again->rejection_reason, $again->verified_by]);
        $this->assertSame(1, User::where('email', 'rina@example.com')->count());
    }

    public function test_pending_or_approved_nim_and_email_still_cannot_be_reused(): void
    {
        $this->register();

        $this->post('/register/alumni', [
            'name' => 'Orang Lain', 'email' => 'rina@example.com', 'nim' => '1410110077', 'graduation_year' => 2018,
            'origin_faculty_id' => $this->fitk->id, 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors(['email', 'nim']);
    }

    public function test_admin_of_another_faculty_cannot_verify(): void
    {
        $alumni = $this->register();

        $this->actingAs($this->adminFebi)->patch("/admin/verifikasi-alumni/{$alumni->id}/setujui")->assertForbidden();
        $this->actingAs($this->adminFebi)->patch("/admin/verifikasi-alumni/{$alumni->id}/tolak", ['reason' => 'Bukan alumni kami'])->assertForbidden();
        $this->actingAs($this->akademik)->patch("/admin/verifikasi-alumni/{$alumni->id}/setujui")->assertForbidden();
        $this->assertSame(AccountStatus::Pending, $alumni->fresh()->account_status);
    }

    public function test_logged_in_user_whose_account_becomes_unapproved_is_logged_out(): void
    {
        $alumni = User::factory()->alumni(2018, $this->fitk)->create();
        $this->actingAs($alumni)->get('/dashboard')->assertOk();

        $alumni->forceFill(['account_status' => AccountStatus::Rejected, 'rejection_reason' => 'Data ganda'])->save();

        $this->actingAs($alumni)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_sso_login_approves_a_pending_row_that_belongs_to_the_real_owner(): void
    {
        config(['sso.driver' => 'fake']);
        $this->seed(FacultySeeder::class);
        // Penyusup mendaftar hidden link memakai NIM mahasiswa aktif yang belum pernah login (masih pending).
        $this->register(['nim' => '2210110001', 'email' => 'penyusup@example.com']);

        $this->post('/login', ['login' => '2210110001', 'password' => 'password'])->assertRedirect('/dashboard');

        $owner = User::where('nim', '2210110001')->firstOrFail();
        $this->assertSame([AccountStatus::Approved, UserType::Mahasiswa], [$owner->account_status, $owner->user_type]);
    }

    public function test_existing_accounts_are_approved_by_the_migration(): void
    {
        // Kolom bawaan = approved: akun yang dibuat tanpa menyebut status (seperti akun lama sebelum M6) langsung disetujui.
        $id = DB::table('users')->insertGetId([
            'name' => 'Alumni Lama', 'email' => 'lama@example.com', 'password' => 'x', 'role' => 'end_user',
            'user_type' => 'alumni', 'graduation_year' => 2015, 'nim' => '1110110001', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame('approved', DB::table('users')->where('id', $id)->value('account_status'));

        // Dan migration M6 sendiri menandai semua baris yang sudah ada sebagai approved.
        DB::table('users')->update(['account_status' => 'pending']);
        $migration = require database_path('migrations/2026_10_28_120000_add_account_status_to_users_table.php');
        $migration->down();
        $migration->up();
        $this->assertSame(0, DB::table('users')->where('account_status', '!=', 'approved')->count());
        $this->assertTrue(User::find($id)->isApproved());
    }
}
