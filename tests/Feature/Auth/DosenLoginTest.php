<?php

namespace Tests\Feature\Auth;

use App\Enums\UserType;
use App\Models\Faculty;
use App\Models\User;
use Database\Seeders\FacultySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Dosen login dengan NIP (18 digit) atau NIDN (10 digit).
 *
 * Latar belakang: PRD memakai NIDN, tetapi koleksi Postman Bridge API v2 menulis field login
 * "NIM/NIP/NIK portal" dan variabel {{nip}} = "NIP dosen"; NIDN tidak disebut.
 * Karena belum pasti, aplikasi menerima keduanya dan selalu menyimpan SATU akun per dosen
 * (kolom users.nidn berisi NIDN bila SSO memberikannya, NIP bila tidak). PERLU DIKONFIRMASI ke pembimbing.
 */
class DosenLoginTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://bridge.test';

    private const BUDI_NIDN = '0412038801';

    private const BUDI_NIP = '198804122015031001';

    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.driver' => 'fake']);
        $this->seed(FacultySeeder::class);
    }

    private function useBridge(): void
    {
        Http::preventStrayRequests();
        config(['sso.driver' => 'bridge', 'sso.bridge_url' => self::BASE]);
    }

    /** Respons Bridge palsu: login selalu berhasil, profil sesuai $data. */
    private function fakeBridge(array $data): void
    {
        Http::fake([
            self::BASE.'/api/v2/portal/login' => Http::response(['success' => true, 'data' => ['token' => 'tok']]),
            self::BASE.'/api/v2/profil' => Http::response(['success' => true, 'data' => $data]),
        ]);
    }

    // ------------------------------------------------------------------ driver fake

    public function test_fake_dosen_can_log_in_with_nip(): void
    {
        $this->post('/login', ['login' => self::BUDI_NIP, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $user = User::where('nidn', self::BUDI_NIDN)->firstOrFail(); // disimpan sebagai NIDN
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserType::Dosen, $user->user_type);
        $this->assertNull($user->nim);
    }

    public function test_nip_and_nidn_lead_to_the_same_account(): void
    {
        $this->post('/login', ['login' => self::BUDI_NIDN, 'password' => 'password'])->assertRedirect('/dashboard');
        $firstId = auth()->id();
        $this->post('/logout');

        $this->post('/login', ['login' => self::BUDI_NIP, 'password' => 'password'])->assertRedirect('/dashboard');

        $this->assertSame($firstId, auth()->id());
        $this->assertSame(1, User::where('user_type', UserType::Dosen)->count());
    }

    public function test_seeded_dosen_logging_in_with_nip_does_not_get_a_second_account(): void
    {
        $this->seed();
        $dosenBefore = User::where('user_type', UserType::Dosen)->count(); // seeder M4 menambah dosen data laporan

        $this->post('/login', ['login' => self::BUDI_NIP, 'password' => 'password'])->assertRedirect('/dashboard');

        $this->assertSame('dosen@uinssc.test', auth()->user()->email);
        $this->assertSame($dosenBefore, User::where('user_type', UserType::Dosen)->count());
    }

    public function test_fake_dosen_without_nidn_is_stored_with_nip(): void
    {
        $this->post('/login', ['login' => '197905152006042001', 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $user = User::where('nidn', '197905152006042001')->firstOrFail();
        $this->assertSame('Dra. Siti Dosen FEBI', $user->name);
        $this->assertSame(UserType::Dosen, $user->user_type);
        $this->assertSame(Faculty::where('code', 'FEBI')->value('id'), $user->origin_faculty_id);
        $this->get('/dashboard')->assertOk();
    }

    public function test_wrong_password_with_nip_is_rejected(): void
    {
        $this->post('/login', ['login' => self::BUDI_NIP, 'password' => 'salah'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    // ------------------------------------------------------------------ driver bridge

    public function test_bridge_receives_the_nip_exactly_as_typed(): void
    {
        $this->useBridge();
        $this->fakeBridge(['nama' => 'Dr. Budi Dosen', 'jenis_user' => 'dosen', 'nip' => self::BUDI_NIP, 'nidn' => self::BUDI_NIDN]);

        $this->post('/login', ['login' => self::BUDI_NIP, 'password' => 'rahasia'])->assertRedirect('/dashboard');

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/api/v2/portal/login'
            && $r['username'] === self::BUDI_NIP);

        // Profil memberi NIDN -> NIDN yang disimpan (sesuai PRD)
        $this->assertSame(self::BUDI_NIDN, auth()->user()->nidn);
    }

    public function test_bridge_profile_with_only_nip_stores_the_nip(): void
    {
        $this->useBridge();
        $this->fakeBridge(['nama' => 'Dr. Budi Dosen', 'jenis_user' => 'dosen', 'nip' => self::BUDI_NIP]);

        $this->post('/login', ['login' => self::BUDI_NIP, 'password' => 'rahasia'])->assertRedirect('/dashboard');

        $this->assertSame(self::BUDI_NIP, auth()->user()->nidn); // 18 karakter, muat di kolom string(20)
    }

    public function test_account_saved_with_nip_switches_to_nidn_without_duplicate(): void
    {
        // Dulu profil SSO hanya memberi NIP, jadi akun tersimpan dengan NIP.
        $old = User::factory()->dosen()->create(['nidn' => self::BUDI_NIP]);

        // Sekarang profil SSO juga memberi NIDN.
        $this->useBridge();
        $this->fakeBridge(['nama' => 'Dr. Budi Dosen', 'jenis_user' => 'dosen', 'nip' => self::BUDI_NIP, 'nidn' => self::BUDI_NIDN]);

        $this->post('/login', ['login' => self::BUDI_NIDN, 'password' => 'rahasia'])->assertRedirect('/dashboard');

        $this->assertSame($old->id, auth()->id());
        $this->assertSame(self::BUDI_NIDN, $old->fresh()->nidn);
        $this->assertSame(1, User::where('user_type', UserType::Dosen)->count());
    }

    public function test_bridge_profile_without_role_or_ids_detects_dosen_from_18_digit_nip(): void
    {
        $this->useBridge();
        $this->fakeBridge(['nama' => 'Dosen Tanpa Field Role']);

        $this->post('/login', ['login' => self::BUDI_NIP, 'password' => 'rahasia'])->assertRedirect('/dashboard');

        $user = auth()->user();
        $this->assertSame(UserType::Dosen, $user->user_type);
        $this->assertSame(self::BUDI_NIP, $user->nidn);
    }
}
