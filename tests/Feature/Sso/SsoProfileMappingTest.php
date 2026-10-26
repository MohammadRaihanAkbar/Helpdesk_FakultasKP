<?php

namespace Tests\Feature\Sso;

use App\Enums\UserType;
use App\Services\Sso\SsoException;
use App\Services\Sso\SsoProfile;
use Tests\TestCase;

/**
 * Pemetaan respons profil -> SsoProfile memakai config('sso.profile').
 * Test ini menjaga perilaku "cadangan" saat bentuk respons berbeda dari asumsi.
 */
class SsoProfileMappingTest extends TestCase
{
    private function map(array $response, string $username = '2210110001'): SsoProfile
    {
        return SsoProfile::fromResponse($response, $username, config('sso.profile'));
    }

    public function test_profile_without_data_wrapper_can_be_mapped_by_changing_root(): void
    {
        config(['sso.profile.root' => null]);

        $profile = $this->map(['nama' => 'Tanpa Wrapper', 'nim' => '2210110001', 'jenis_user' => 'mahasiswa']);

        $this->assertSame('Tanpa Wrapper', $profile->name);
        $this->assertSame(UserType::Mahasiswa, $profile->userType);
    }

    public function test_alternative_field_names_are_tried_in_order(): void
    {
        $profile = $this->map(['data' => [
            'name' => 'Nama Alternatif',
            'role' => 'Mahasiswa',
            'status' => 'LULUS',
            'tahun_lulus' => 2024,
            'kode_fakultas' => 'fitk',
        ]], '2010110009');

        $this->assertSame('Nama Alternatif', $profile->name);
        $this->assertSame(UserType::Alumni, $profile->userType);
        $this->assertSame(2024, $profile->graduationYear);
        $this->assertSame('2010110009', $profile->nim); // tidak ada field nim -> pakai username
        $this->assertSame('FITK', $profile->facultyCode);
    }

    public function test_dosen_without_nidn_is_identified_by_nip_only(): void
    {
        $profile = $this->map(['data' => ['nama' => 'Dosen NIP', 'jenis_user' => 'dosen', 'nip' => '198804122015031001']], '198804122015031001');

        $this->assertSame(UserType::Dosen, $profile->userType);
        $this->assertSame('198804122015031001', $profile->nip);
        $this->assertNull($profile->nidn); // M5: kolom nidn tidak lagi diisi NIP
        $this->assertSame('nip', $profile->identifierColumn());
    }

    public function test_dosen_profile_without_any_id_uses_the_typed_username_as_nip(): void
    {
        $profile = $this->map(['data' => ['nama' => 'Dosen', 'jenis_user' => 'dosen']], '0412038801');

        $this->assertSame('0412038801', $profile->nip);
        $this->assertNull($profile->nidn);
    }

    public function test_dosen_logging_in_with_nidn_keeps_nip_from_profile(): void
    {
        $profile = $this->map(['data' => ['nama' => 'Dosen', 'jenis_user' => 'dosen', 'nidn' => '0412038801', 'nip' => '198804122015031001']], '0412038801');

        $this->assertSame('198804122015031001', $profile->identifier());
        $this->assertSame('0412038801', $profile->nidn);
    }

    public function test_kind_is_detected_from_username_format_when_role_is_missing(): void
    {
        $dosen = $this->map(['data' => ['nama' => 'Dosen']], '0412038801');
        $mahasiswa = $this->map(['data' => ['nama' => 'Mahasiswa']], '2210110001');

        $this->assertSame(UserType::Dosen, $dosen->userType);
        $this->assertSame(UserType::Mahasiswa, $mahasiswa->userType);
    }

    public function test_faculty_code_map_translates_sso_codes(): void
    {
        config(['sso.profile.faculty_code_map' => ['FTK' => 'FITK']]);

        $profile = $this->map(['data' => ['nama' => 'X', 'jenis_user' => 'mahasiswa', 'fakultas' => ['kode' => 'FTK']]]);

        $this->assertSame('FITK', $profile->facultyCode);
    }

    public function test_non_student_non_lecturer_account_is_rejected(): void
    {
        $this->expectException(SsoException::class);
        $this->expectExceptionMessage('tidak terdaftar sebagai mahasiswa, alumni, atau dosen');

        $this->map(['data' => ['nama' => 'Tendik', 'jenis_user' => 'pegawai']], '3201010101010001');
    }

    public function test_unreadable_profile_is_rejected(): void
    {
        $this->expectException(SsoException::class);

        $this->map(['success' => true, 'message' => 'ok']);
    }
}
