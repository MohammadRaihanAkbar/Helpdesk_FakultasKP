<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validasi registrasi alumni lulus <= 2021 lewat hidden link (US-01B, PRD 9.3).
 */
class AlumniRegistrationRequest extends FormRequest
{
    /** Tahun lulus terakhir yang boleh mendaftar lewat hidden link. 2022 ke atas ada di SSO. */
    public const LAST_GRADUATION_YEAR = 2021;

    /** Pesan PRD untuk kode error ALUMNI_USE_SSO (tahun >= 2022 atau NIM sudah terdaftar). */
    public const ALUMNI_USE_SSO_MESSAGE = 'Alumni lulusan 2022 ke atas atau NIM yang sudah terdaftar silakan login melalui SSO Kampus menggunakan NIM.';

    public function authorize(): bool
    {
        return true; // akses ke halaman sudah dijaga middleware (guest + kunci hidden link)
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'nim' => trim((string) $this->input('nim')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            // Format NIM lama: hanya angka, 8-20 digit (ASUMSI, kolom nim maksimal 20 karakter).
            'nim' => ['required', 'regex:/^\d{8,20}$/', 'unique:users,nim'],
            'graduation_year' => ['required', 'integer', 'min:1960', 'max:'.self::LAST_GRADUATION_YEAR],
            // Hanya fakultas yang aktif yang boleh dipilih sebagai Fakultas Asal.
            'origin_faculty_id' => ['required', 'integer', Rule::exists('faculties', 'id')->where('is_active', true)],
            // Password::defaults() = minimal 8 karakter (lihat AppServiceProvider). confirmed -> wajib ada password_confirmation yang sama.
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Pesan khusus yang berbeda dari pesan umum di lang/id/validation.php.
     * Format kuncinya: 'field.rule'.
     */
    public function messages(): array
    {
        return [
            'graduation_year.max' => self::ALUMNI_USE_SSO_MESSAGE,
            'nim.unique' => self::ALUMNI_USE_SSO_MESSAGE,
            'nim.regex' => 'NIM harus berupa angka 8 sampai 20 digit.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan login dengan email tersebut.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'email' => 'email pribadi',
            'nim' => 'NIM lama',
            'graduation_year' => 'tahun lulus',
            'origin_faculty_id' => 'fakultas asal',
        ];
    }
}
