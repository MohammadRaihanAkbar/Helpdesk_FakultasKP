<?php

namespace Tests\Feature\Sso;

use App\Enums\UserType;
use App\Services\Sso\FakeSsoClient;
use App\Services\Sso\SsoException;
use Tests\TestCase;

/**
 * FakeSsoClient: simulasi SSO dari config('sso.fake_users').
 */
class FakeSsoClientTest extends TestCase
{
    private function client(): FakeSsoClient
    {
        return new FakeSsoClient(config('sso'));
    }

    public function test_fixture_users_are_mapped_to_the_right_user_type(): void
    {
        $expected = [
            '2210110001' => UserType::Mahasiswa,
            '0412038801' => UserType::Dosen,
            '1910110045' => UserType::Alumni,
            '2310210007' => UserType::Mahasiswa,
        ];

        foreach ($expected as $username => $type) {
            $username = (string) $username; // PHP mengubah kunci array berupa angka menjadi integer
            $profile = $this->client()->authenticate($username, 'password');

            $this->assertSame($type, $profile->userType, "Jenis pelapor {$username} salah");
            $this->assertSame($username, $profile->identifier());
            $this->assertNotEmpty($profile->token);
        }
    }

    public function test_alumni_profile_contains_graduation_year_and_faculty(): void
    {
        $profile = $this->client()->authenticate('1910110045', 'password');

        $this->assertSame(2023, $profile->graduationYear);
        $this->assertSame('FITK', $profile->facultyCode);
        $this->assertSame('Tadris Matematika', $profile->programStudi);
        $this->assertSame('nim', $profile->identifierColumn());
    }

    public function test_wrong_password_is_rejected(): void
    {
        try {
            $this->client()->authenticate('2210110001', 'salah');
            $this->fail('Seharusnya melempar SsoException');
        } catch (SsoException $e) {
            $this->assertSame(SsoException::INVALID_CREDENTIALS, $e->errorCode);
            $this->assertSame('NIM/NIDN atau password salah.', $e->getMessage());
        }
    }

    public function test_unknown_user_is_rejected(): void
    {
        // Alumni lulus 2019 memang tidak ada di SSO.
        $this->expectException(SsoException::class);

        $this->client()->authenticate('1510110023', 'password');
    }
}
