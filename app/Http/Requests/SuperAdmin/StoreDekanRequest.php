<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * [M6] Buat akun lokal Dekan oleh Super Admin (dekan yang belum pernah login SSO).
 * Dekan login dengan email + password ini di halaman login biasa.
 */
class StoreDekanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin(); // route juga dijaga role:super_admin
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'nip' => trim((string) $this->input('nip')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'faculty_id' => ['required', 'integer', Rule::exists('faculties', 'id')],
            'nip' => ['nullable', 'regex:/^\d{18}$/', 'unique:users,nip'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah dipakai akun lain. Kalau dekan sudah pernah login, tunjuk akunnya dari daftar Pengguna.',
            'nip.unique' => 'NIP ini sudah dipakai akun lain (dekan mungkin sudah pernah login SSO). Tunjuk akun itu dari daftar Pengguna.',
            'nip.regex' => 'NIP harus 18 digit angka.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama lengkap', 'faculty_id' => 'fakultas', 'nip' => 'NIP'];
    }
}
