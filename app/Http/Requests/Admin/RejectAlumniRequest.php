<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** [M6] Tolak pendaftaran alumni: alasan WAJIB (dikirim ke email pendaftar dan tampil saat ia mencoba login). */
class RejectAlumniRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin per akun dicek UserPolicy::verifyAlumni di controller
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:500']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Tulis alasan penolakan. Alasan ini dikirim ke email pendaftar.'];
    }

    public function attributes(): array
    {
        return ['reason' => 'alasan penolakan'];
    }
}
