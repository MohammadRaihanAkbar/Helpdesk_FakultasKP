<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Faculty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Form tambah/ubah fakultas (US-17): Kode, Nama, Slug, Deskripsi, Aktif.
 *
 * - Kode disimpan HURUF BESAR (FITK, FEBI, ...) dan harus unik (US-17 "Sistem memvalidasi keunikan kode").
 *   Kode yang sama juga dipakai untuk mencocokkan kode fakultas dari SSO (config/sso.php -> faculty_code_map).
 * - Slug (dipakai di URL) boleh dikosongkan: otomatis = kode huruf kecil. Juga harus unik.
 * - Saat ubah, aturan unique mengabaikan fakultas itu sendiri (Rule::unique()->ignore()).
 */
class FacultyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route dijaga middleware role:super_admin, controller memanggil FacultyPolicy
    }

    protected function prepareForValidation(): void
    {
        $code = is_string($this->code) ? Str::upper(trim($this->code)) : $this->code;
        $slug = is_string($this->slug) && trim($this->slug) !== '' ? Str::slug(trim($this->slug)) : (is_string($code) ? Str::slug($code) : null);

        $this->merge([
            'code' => $code,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'slug' => $slug,
            'description' => is_string($this->description) && trim($this->description) !== '' ? trim($this->description) : null,
        ]);
    }

    public function rules(): array
    {
        /** @var Faculty|null $faculty */
        $faculty = $this->route('faculty');

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/', Rule::unique('faculties', 'code')->ignore($faculty)],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('faculties', 'slug')->ignore($faculty)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Kode fakultas ini sudah dipakai fakultas lain.',
            'code.regex' => 'Kode fakultas hanya boleh huruf dan angka tanpa spasi, contoh FITK.',
            'slug.unique' => 'Slug ini sudah dipakai fakultas lain.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode fakultas',
            'name' => 'nama fakultas',
            'description' => 'deskripsi',
            'is_active' => 'status aktif',
        ];
    }
}
