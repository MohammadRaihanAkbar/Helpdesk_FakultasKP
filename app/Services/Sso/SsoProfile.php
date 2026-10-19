<?php

namespace App\Services\Sso;

use App\Enums\UserType;

/**
 * DTO (Data Transfer Object): wadah data profil SSO yang SUDAH dirapikan.
 *
 * Kenapa perlu DTO, bukan langsung array respons Bridge?
 *   Bentuk respons Bridge belum pasti. Semua "tebakan" nama field dikumpulkan di satu tempat
 *   (fromResponse() + config/sso.php). Kode lain (SsoUserSynchronizer) cukup membaca
 *   properti yang jelas: $profile->nim, $profile->userType, ... tanpa peduli bentuk aslinya.
 *
 * readonly = nilainya tidak bisa diubah setelah dibuat (aman dioper ke mana-mana).
 */
final class SsoProfile
{
    public function __construct(
        public readonly string $username,
        public readonly UserType $userType,
        public readonly string $name,
        public readonly ?string $email = null,
        public readonly ?string $nim = null,
        public readonly ?string $nidn = null,
        public readonly ?int $graduationYear = null,
        public readonly ?string $facultyCode = null,
        public readonly ?string $programStudi = null,
        public readonly ?string $token = null,
        public readonly array $raw = [],
        public readonly ?string $nip = null,
    ) {}

    /** Nilai identitas utama: NIM untuk mahasiswa/alumni, NIDN untuk dosen. */
    public function identifier(): string
    {
        return $this->userType === UserType::Dosen ? (string) $this->nidn : (string) $this->nim;
    }

    /**
     * Semua nomor yang mungkin sudah tersimpan untuk orang ini (untuk mencari akun lama).
     *
     * Dosen bisa login memakai NIDN maupun NIP. Kolom users.nidn berisi NIDN bila SSO memberikannya,
     * dan NIP bila tidak. Supaya dosen yang dulu tersimpan dengan NIP tidak dibuatkan akun kedua
     * saat SSO mulai mengirim NIDN (atau sebaliknya), akun dicari dengan semua nomor ini.
     *
     * @return list<string>
     */
    public function identifierCandidates(): array
    {
        if ($this->userType !== UserType::Dosen) {
            return [(string) $this->nim];
        }

        return array_values(array_unique(array_filter(
            [$this->nidn, $this->nip, $this->username],
            fn (?string $value) => $value !== null && $value !== '',
        )));
    }

    /** Nama kolom identitas di tabel users: 'nim' atau 'nidn'. */
    public function identifierColumn(): string
    {
        return $this->userType === UserType::Dosen ? 'nidn' : 'nim';
    }

    /**
     * Ubah respons GET /api/v2/profil menjadi SsoProfile, memakai pemetaan config('sso.profile').
     *
     * @param  array  $response  JSON respons profil (sudah di-decode jadi array)
     * @param  string  $username  username yang diketik di form login
     * @param  array  $mapping  isi config('sso.profile')
     *
     * @throws SsoException
     */
    public static function fromResponse(array $response, string $username, array $mapping, ?string $token = null): self
    {
        $root = $mapping['root'] ?? null;
        $data = $root ? data_get($response, $root) : $response;

        if (! is_array($data) || $data === []) {
            throw SsoException::invalidProfile('objek profil tidak ditemukan');
        }

        $fields = $mapping['fields'] ?? [];
        $get = fn (string $field) => self::firstFilled($data, (array) ($fields[$field] ?? []));

        $nim = $get('nim');
        // NIDN diutamakan (PRD); NIP dipakai sebagai cadangan karena login Bridge tertulis "NIM/NIP/NIK".
        // NIP juga disimpan terpisah di $nip, supaya akun lama bisa ditemukan dengan NIP maupun NIDN.
        $nip = $get('nip');
        $nidn = $get('nidn') ?? $nip;
        $status = self::lower($get('status'));
        $graduationYear = $get('graduation_year');
        $graduationYear = is_numeric($graduationYear) ? (int) $graduationYear : null;

        $kind = self::detectKind(self::lower($get('role')), $nim, $nidn, $username, $mapping);

        if ($kind === 'dosen') {
            $userType = UserType::Dosen;
            $nidn ??= $username;      // profil tidak memberi NIDN/NIP -> pakai yang diketik
            // Dosen yang login dengan NIP 18 digit tapi profilnya tanpa field nip: NIP = yang diketik.
            if ($nip === null && preg_match('/^\d{18}$/', $username)) {
                $nip = $username;
            }
            $nim = null;
            $graduationYear = null;
        } else {
            $isGraduated = in_array($status, $mapping['graduated_status_values'] ?? [], true)
                || ($status === null && $graduationYear !== null);
            $userType = $isGraduated ? UserType::Alumni : UserType::Mahasiswa;
            $nim ??= $username;
            $nidn = null;
            $nip = null;
            if (! $isGraduated) {
                $graduationYear = null;
            }
        }

        $name = $get('name');
        if ($name === null) {
            throw SsoException::invalidProfile('nama kosong');
        }

        $facultyCode = $get('faculty_code');
        if ($facultyCode !== null) {
            $facultyCode = strtoupper($facultyCode);
            $facultyCode = $mapping['faculty_code_map'][$facultyCode] ?? $facultyCode;
        }

        return new self(
            username: $username,
            userType: $userType,
            name: $name,
            email: $get('email'),
            nim: $nim,
            nidn: $nidn,
            graduationYear: $graduationYear,
            facultyCode: $facultyCode,
            programStudi: $get('program_studi'),
            token: $token,
            raw: $data,
            nip: $nip,
        );
    }

    /**
     * Tentukan jenis akun: 'mahasiswa' (termasuk alumni) atau 'dosen'.
     * Urutan: field role di profil -> ada NIM/NIDN di profil -> format username.
     */
    private static function detectKind(?string $role, ?string $nim, ?string $nidn, string $username, array $mapping): string
    {
        if ($role !== null) {
            foreach ($mapping['role_values'] ?? [] as $kind => $values) {
                if (in_array($role, $values, true)) {
                    return $kind;
                }
            }

            // Field role ADA tapi nilainya bukan mahasiswa/dosen (mis. "pegawai") -> tolak.
            throw SsoException::unsupportedAccount();
        }

        if ($nim !== null) {
            return 'mahasiswa';
        }
        if ($nidn !== null) {
            return 'dosen';
        }

        foreach ($mapping['username_patterns'] ?? [] as $kind => $pattern) {
            if (preg_match($pattern, $username)) {
                return $kind;
            }
        }

        throw SsoException::unsupportedAccount();
    }

    /** Ambil nilai pertama yang terisi dari beberapa kemungkinan dot path. */
    private static function firstFilled(array $data, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($data, $path);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private static function lower(?string $value): ?string
    {
        return $value === null ? null : mb_strtolower($value);
    }
}
