<?php

/*
|--------------------------------------------------------------------------
| Pesan Validasi (Bahasa Indonesia)
|--------------------------------------------------------------------------
|
| Laravel memilih file ini karena APP_LOCALE=id. ":attribute" diganti nama
| field (lihat bagian 'attributes' di bawah); ":Attribute" sama, tetapi huruf
| pertamanya dibuat kapital (untuk awal kalimat). ":max", ":min", dst. diganti
| parameter aturannya. Aturan yang belum ada di sini akan memakai pesan
| bahasa Inggris bawaan Laravel (fallback_locale = en), jadi tambahkan saja
| bila nanti memakai aturan baru.
|
*/

return [

    'accepted' => ':Attribute harus disetujui.',
    'after' => ':Attribute harus berisi tanggal setelah :date.',
    'after_or_equal' => ':Attribute harus berisi tanggal setelah atau sama dengan :date.',
    'alpha_dash' => ':Attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'array' => ':Attribute harus berupa daftar.',
    'before' => ':Attribute harus berisi tanggal sebelum :date.',
    'before_or_equal' => ':Attribute harus berisi tanggal sebelum atau sama dengan :date.',
    'between' => [
        'array' => ':Attribute harus berisi antara :min sampai :max item.',
        'file' => ':Attribute harus berukuran antara :min sampai :max kilobyte.',
        'numeric' => ':Attribute harus bernilai antara :min sampai :max.',
        'string' => ':Attribute harus berisi antara :min sampai :max karakter.',
    ],
    'boolean' => ':Attribute harus bernilai ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => ':Attribute bukan tanggal yang valid.',
    'digits' => ':Attribute harus terdiri dari :digits digit.',
    'digits_between' => ':Attribute harus terdiri dari :min sampai :max digit.',
    'email' => ':Attribute harus berupa alamat email yang valid.',
    'enum' => ':Attribute yang dipilih tidak valid.',
    'extensions' => ':Attribute harus berakhiran salah satu dari: :values.',
    'exists' => ':Attribute yang dipilih tidak valid.',
    'file' => ':Attribute harus berupa berkas.',
    'image' => ':Attribute harus berupa gambar.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'integer' => ':Attribute harus berupa bilangan bulat.',
    'max' => [
        'array' => ':Attribute maksimal berisi :max item.',
        'file' => ':Attribute maksimal berukuran :max kilobyte.',
        'numeric' => ':Attribute maksimal bernilai :max.',
        'string' => ':Attribute maksimal berisi :max karakter.',
    ],
    'mimes' => ':Attribute harus berupa berkas berjenis: :values.',
    'mimetypes' => ':Attribute harus berupa berkas berjenis: :values.',
    'min' => [
        'array' => ':Attribute minimal berisi :min item.',
        'file' => ':Attribute minimal berukuran :min kilobyte.',
        'numeric' => ':Attribute minimal bernilai :min.',
        'string' => ':Attribute minimal berisi :min karakter.',
    ],
    'not_in' => ':Attribute yang dipilih tidak valid.',
    'numeric' => ':Attribute harus berupa angka.',
    'password' => [
        'letters' => ':Attribute harus mengandung minimal satu huruf.',
        'mixed' => ':Attribute harus mengandung minimal satu huruf besar dan satu huruf kecil.',
        'numbers' => ':Attribute harus mengandung minimal satu angka.',
        'symbols' => ':Attribute harus mengandung minimal satu simbol.',
        'uncompromised' => ':Attribute ini pernah bocor di internet. Silakan pilih :attribute lain.',
    ],
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':Attribute wajib diisi.',
    'required_if' => ':Attribute wajib diisi jika :other adalah :value.',
    'required_with' => ':Attribute wajib diisi jika :values diisi.',
    'required_without' => ':Attribute wajib diisi jika :values tidak diisi.',
    'same' => ':Attribute dan :other harus sama.',
    'size' => [
        'array' => ':Attribute harus berisi :size item.',
        'file' => ':Attribute harus berukuran :size kilobyte.',
        'numeric' => ':Attribute harus bernilai :size.',
        'string' => ':Attribute harus berisi :size karakter.',
    ],
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute sudah digunakan.',
    'uploaded' => ':Attribute gagal diunggah.',
    'url' => ':Attribute harus berupa URL yang valid.',

    /*
    | Pesan khusus per field (opsional). Pesan yang hanya berlaku di satu form
    | lebih baik ditulis di method messages() Form Request-nya.
    */
    'custom' => [],

    /*
    | Nama field yang tampil di pesan, menggantikan nama kolom
    | (mis. "graduation_year wajib diisi" -> "tahun lulus wajib diisi").
    */
    'attributes' => [
        'login' => 'NIM / NIP / Email',
        'name' => 'nama',
        'email' => 'email',
        'password' => 'password',
        'password_confirmation' => 'konfirmasi password',
        'nim' => 'NIM',
        'nidn' => 'NIDN',
        'nip' => 'NIP',
        'graduation_year' => 'tahun lulus',
        'origin_faculty_id' => 'fakultas asal',
        'faculty_id' => 'fakultas',
        'category_id' => 'kategori',
        'title' => 'judul',
        'description' => 'deskripsi',
        'body' => 'pesan',
        'score' => 'skor',
        'comment' => 'komentar',
        'rejection_reason' => 'alasan penolakan',
        'sla_hours' => 'target SLA (jam)',
        'attachments' => 'lampiran',
        'attachments.*' => 'lampiran',
        'category_ids' => 'spesialisasi',
        'remember' => 'ingat saya',
    ],

];
