<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserType;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi form kategori (tambah & ubah) di Pengaturan Layanan.
 *
 * Form Request ini hanya mengecek FORMAT isian (wajib, angka, panjang, unik).
 * Aturan bisnis yang butuh melihat data lain (induk sah? kategori standar? sudah dipakai tiket?)
 * dicek di App\Services\CategoryManager.
 *
 * Satu class untuk dua route:
 *   POST /admin/services             -> tambah kategori tambahan  ($category = null)
 *   PUT  /admin/services/{category}  -> ubah kategori            ($category = model dari route)
 */
class CategoryRequest extends FormRequest
{
    /** Batas Target SLA: 1 jam s.d. 720 jam (30 hari). Keputusan teknis, PRD tidak menentukan batas atas. */
    public const SLA_MAX = 720;

    public function authorize(): bool
    {
        // Izin (admin & fakultas yang sama) sudah dicek controller lewat CategoryPolicy.
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Hanya merapikan field yang MEMANG dikirim. Field yang tidak dikirim jangan ditambahkan,
        // supaya PUT yang cuma berisi sla_hours tidak ikut mengubah status aktif / induk.
        $clean = [];

        if (is_string($this->input('name'))) {
            $clean['name'] = trim(preg_replace('/\s+/', ' ', $this->input('name')));
        }

        if ($this->has('is_active')) {
            // Form mengirim hidden input "0" + checkbox "1", jadi nilainya selalu ada.
            $clean['is_active'] = $this->boolean('is_active');
        }

        foreach (['parent_id', 'sla_hours'] as $field) {
            if ($this->input($field) === '') {
                $clean[$field] = null; // input kosong di form = tidak diisi
            }
        }

        $this->merge($clean);
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $facultyId = $this->user()->faculty_id;

        $sla = ['integer', 'min:1', 'max:'.self::SLA_MAX];
        $common = [
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];

        // Kategori STANDAR: hanya SLA, deskripsi, Aktif. Field terkunci tetap "dikenali" (sometimes)
        // supaya CategoryManager bisa menolak dengan pesan STANDARD_CATEGORY_LOCKED bila nilainya diubah.
        if ($category instanceof Category && $category->is_standard) {
            return $common + [
                'sla_hours' => ['required', ...$sla],
                'name' => ['sometimes', 'nullable', 'string'],
                'parent_id' => ['sometimes', 'nullable'],
                'audiences' => ['sometimes', 'nullable', 'array'],
            ];
        }

        // Kategori TAMBAHAN (baru atau diedit)
        return $common + [
            'name' => [
                'required', 'string', 'max:100',
                // Unik per fakultas (termasuk tidak boleh memakai nama kategori standar), sama dengan UNIQUE di migration.
                Rule::unique('categories', 'name')->where('faculty_id', $facultyId)->ignore($category?->id),
            ],
            'parent_id' => ['nullable', 'integer'],
            // Kelompok baru (tanpa induk): SLA & audiens wajib. Jenis Masalah: SLA boleh kosong (= SLA induk).
            'sla_hours' => ['nullable', 'required_without:parent_id', ...$sla],
            'audiences' => ['nullable', 'required_without:parent_id', 'array'],
            'audiences.*' => [Rule::enum(UserType::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'audiences.required_without' => 'Pilih minimal satu audiens (Mahasiswa, Dosen, atau Alumni) untuk Kelompok Layanan.',
            'sla_hours.required_without' => 'Target SLA (jam) wajib diisi untuk Kelompok Layanan.',
            'name.unique' => 'Nama kategori ini sudah dipakai di fakultas Anda (termasuk nama kategori standar).',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama kategori',
            'parent_id' => 'kelompok induk',
            'audiences' => 'audiens',
            'audiences.*' => 'audiens',
            'is_active' => 'status aktif',
        ];
    }
}
