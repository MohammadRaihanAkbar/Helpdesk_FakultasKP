<?php

namespace Tests\Feature\Sso;

use App\Enums\Role;
use App\Enums\UserType;
use App\Models\Faculty;
use App\Models\User;
use App\Services\Sso\SsoProfile;
use App\Services\Sso\SsoUserSynchronizer;
use Database\Seeders\FacultySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SsoUserSynchronizer: profil SSO -> baris tabel users.
 */
class SsoUserSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FacultySeeder::class);
    }

    private function sync(SsoProfile $profile): User
    {
        return app(SsoUserSynchronizer::class)->sync($profile);
    }

    private function mahasiswa(array $overrides = []): SsoProfile
    {
        return new SsoProfile(...array_merge([
            'username' => '2210110001',
            'userType' => UserType::Mahasiswa,
            'name' => 'Ahmad Mahasiswa',
            'email' => 'mahasiswa@uinssc.test',
            'nim' => '2210110001',
            'facultyCode' => 'FITK',
            'programStudi' => 'Pendidikan Agama Islam',
        ], $overrides));
    }

    public function test_new_profile_creates_an_active_end_user(): void
    {
        $user = $this->sync($this->mahasiswa());

        $this->assertTrue($user->exists);
        $this->assertSame(Role::EndUser, $user->role);
        $this->assertSame(UserType::Mahasiswa, $user->user_type);
        $this->assertTrue($user->is_active);
        $this->assertSame(Faculty::where('code', 'FITK')->value('id'), $user->origin_faculty_id);
        $this->assertNull($user->faculty_id);
        // Password SSO tidak disimpan: akun baru mendapat password acak.
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_existing_user_is_updated_not_duplicated(): void
    {
        $existing = User::factory()->create(['nim' => '2210110001', 'name' => 'Nama Lama']);

        $user = $this->sync($this->mahasiswa(['userType' => UserType::Alumni, 'graduationYear' => 2024]));

        $this->assertTrue($user->is($existing));
        $this->assertSame('Ahmad Mahasiswa', $user->name);
        $this->assertSame(UserType::Alumni, $user->user_type);
        $this->assertSame(2024, $user->graduation_year);
        $this->assertSame(1, User::count());
    }

    public function test_role_and_assigned_faculty_are_never_changed(): void
    {
        $fua = Faculty::where('code', 'FUA')->first();
        $admin = User::factory()->dosen()->create(['nidn' => '0412038801', 'role' => Role::Admin, 'faculty_id' => $fua->id]);

        $user = $this->sync(new SsoProfile(
            username: '0412038801', userType: UserType::Dosen, name: 'Dr. Budi Dosen', nidn: '0412038801', facultyCode: 'FITK',
        ));

        $this->assertTrue($user->is($admin));
        $this->assertSame(Role::Admin, $user->role);
        $this->assertSame($fua->id, $user->faculty_id);
        $this->assertSame(Faculty::where('code', 'FITK')->value('id'), $user->origin_faculty_id);
    }

    public function test_missing_email_uses_fallback_domain(): void
    {
        config(['sso.email_fallback_domain' => 'sso.contoh.ac.id']);

        $user = $this->sync($this->mahasiswa(['email' => null]));

        $this->assertSame('2210110001@sso.contoh.ac.id', $user->email);
    }

    public function test_email_taken_by_another_account_is_not_reused(): void
    {
        config(['sso.email_fallback_domain' => 'sso.contoh.ac.id']);
        User::factory()->staff()->create(['email' => 'mahasiswa@uinssc.test']);

        $user = $this->sync($this->mahasiswa());

        $this->assertSame('2210110001@sso.contoh.ac.id', $user->email);
    }

    public function test_unknown_faculty_code_keeps_the_old_origin_faculty(): void
    {
        $febi = Faculty::where('code', 'FEBI')->value('id');
        User::factory()->create(['nim' => '2210110001', 'origin_faculty_id' => $febi]);

        $user = $this->sync($this->mahasiswa(['facultyCode' => 'TIDAKADA']));

        $this->assertSame($febi, $user->origin_faculty_id);
    }

    // ---------- [M6] email dari SSO = alamat tujuan email notifikasi ----------

    public function test_email_is_refreshed_from_sso_on_every_login(): void
    {
        $this->sync($this->mahasiswa(['email' => null])); // login pertama: Bridge belum mengirim email
        $this->assertSame('2210110001@'.config('sso.email_fallback_domain'), User::where('nim', '2210110001')->value('email'));
        $this->assertFalse(User::where('nim', '2210110001')->first()->canReceiveEmail());

        $user = $this->sync($this->mahasiswa(['email' => 'Ahmad.Baru@Gmail.com']));
        $this->assertSame('ahmad.baru@gmail.com', $user->email);
        $this->assertTrue($user->canReceiveEmail());

        $user = $this->sync($this->mahasiswa(['email' => 'ahmad@uinssc.ac.id']));
        $this->assertSame('ahmad@uinssc.ac.id', $user->email);
        $this->assertSame(1, User::where('nim', '2210110001')->count());
    }

    public function test_real_email_is_never_replaced_by_the_placeholder_or_an_invalid_value(): void
    {
        $this->sync($this->mahasiswa(['email' => 'ahmad@gmail.com']));

        foreach ([null, '', 'bukan-email', '2210110001@'.config('sso.email_fallback_domain')] as $email) {
            $this->assertSame('ahmad@gmail.com', $this->sync($this->mahasiswa(['email' => $email]))->email);
        }
    }
}
