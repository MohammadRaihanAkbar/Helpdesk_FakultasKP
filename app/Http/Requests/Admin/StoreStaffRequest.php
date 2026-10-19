<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Form "Tambah Staff Baru" (US-12, PRD 4.7): Nama Lengkap, Email, Password Awal.
 * faculty_id TIDAK diambil dari form: selalu fakultas admin yang login (diisi controller).
 */
class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route sudah dijaga middleware role:admin
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah dipakai akun lain.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'password' => 'password awal',
        ];
    }
}
