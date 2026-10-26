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

    /** Nilai identitas utama: NIM untuk mahasiswa/alumni, NIP untuk dosen (NIDN hanya bila NIP tidak ada). */
    public function identifier(): string
    {
        return $this->userType === UserType::Dosen ? (string) ($this->nip ?? $this->nidn) : (string) $this->nim;
    }

    /**
     * Semua nomor yang mungkin sudah tersimpan untuk orang ini (untuk mencari akun lama).
     *
     * Dosen bisa login memakai NIP maupun NIDN. Sejak M5, NIP disimpan di kolom users.nip dan NIDN di users.nidn
     * (hanya bila SSO memberikannya). Akun lama dari M1–M4 bisa saja masih menyimpan NIP di kolom nidn, jadi akun
     * dicari dengan semua nomor ini di KEDUA kolom (lihat SsoUserSynchronizer::findExisting()).
     *
     * @return list<string>
     */
    public function identifierCandidates(): array
    {
        if ($this->userType !== UserType::Dosen) {
            return [(string) $this->nim];
        }

        return array_values(array_unique(array_filter(
            [$this->nip, $this->nidn, $this->username],
            fn (?string $value) => $value !== null && $value !== '',
        )));
    }

    /** Nama kolom identitas utama di tabel users: 'nim', 'nip', atau 'nidn' (dosen tanpa NIP). */
    public function identifierColumn(): string
    {
        if ($this->userType !== UserType::Dosen) {
            return 'nim';
        }

        return $this->nip !== null ? 'nip' : 'nidn';
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
        // M5: dosen diidentifikasi dengan NIP (login Bridge tertulis "NIM/NIP/NIK").
        // NIDN hanya disimpan kalau profil SSO memang mengirimnya; tidak lagi diisi NIP sebagai cadangan.
        $nip = $get('nip');
        $nidn = $get('nidn');
        $status = self::lower($get('status'));
        $graduationYear = $get('graduation_year');
        $graduationYear = is_numeric($graduationYear) ? (int) $graduationYear : null;

        $kind = self::detectKind(self::lower($get('role')), $nim, $nip ?? $nidn, $username, $mapping);

        if ($kind === 'dosen') {
            $userType = UserType::Dosen;
            // Profil tanpa field nip: yang diketik di form login dianggap NIP, karena Bridge menerima NIP.
            // Pengecualian: yang diketik sama dengan NIDN dari profil (dosen login memakai NIDN).
            if ($nip === null && $username !== $nidn && ($nidn === null || preg_match('/^\d{18}$/', $username))) {
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
     * Urutan: field role di profil -> ada NIM / NIP / NIDN di profil -> format username.
     */
    private static function detectKind(?string $role, ?string $nim, ?string $lecturerId, string $username, array $mapping): string
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
        if ($lecturerId !== null) {
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
