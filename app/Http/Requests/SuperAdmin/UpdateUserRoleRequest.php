<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Tunjuk / cabut Admin Fakultas (US-18).
 *
 *   action=appoint + faculty_id  -> jadikan Admin Fakultas di fakultas itu (juga dipakai untuk memindah fakultas Admin)
 *   action=revoke                -> kembalikan menjadi Pelapor (end_user)
 *
 * Hanya dua isian ini yang dibaca. Kolom lain (user_type, email, ...) tidak bisa ikut diubah dari form ini.
 */
class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin per akun dicek UserPolicy::changeRole di controller
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:appoint,revoke'],
            'faculty_id' => ['required_if:action,appoint', 'nullable', 'integer', 'exists:faculties,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'faculty_id.required_if' => 'Pilih fakultas tempat Admin ini bertugas.',
        ];
    }

    public function attributes(): array
    {
        return ['faculty_id' => 'fakultas', 'action' => 'aksi'];
    }
}
