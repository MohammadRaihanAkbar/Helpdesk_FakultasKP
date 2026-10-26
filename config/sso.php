<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi SSO Kampus (Bridge API v2)
|--------------------------------------------------------------------------
|
| Login mahasiswa, dosen, dan alumni lulus >= 2022 memakai akun SSO kampus
| lewat Bridge API v2 (lihat docs/bridge-api-v2.postman.json):
|
|   1. POST {bridge_url}/api/v2/portal/login   (form: username, password)
|        -> token di "data.token" atau "token"
|   2. GET  {bridge_url}/api/v2/profil          (header Authorization: Bearer {token})
|        -> data profil pengguna
|   3. POST {bridge_url}/api/v2/logout          (saat logout, best effort)
|
| PENTING — BENTUK RESPONS /profil BELUM DIKETAHUI.
| Koleksi Postman tidak menyertakan contoh respons. Karena itu nama-nama field
| di bagian 'profile' di bawah adalah TEBAKAN yang wajar, dan sengaja dibuat bisa
| diubah tanpa menyentuh kode PHP. Begitu ada contoh respons asli, cukup sesuaikan
| bagian 'profile' (dan fixture 'fake_users' supaya bentuknya sama). Panduan
| lengkapnya ada di docs/SSO.md.
|
*/

return [

    /*
    | Driver yang dipakai:
    |   'fake'   -> simulasi lokal dari 'fake_users' di bawah (default untuk development/Laragon)
    |   'bridge' -> benar-benar memanggil Bridge API kampus
    */
    'driver' => env('SSO_DRIVER', 'fake'),

    /* Base URL Bridge, tanpa garis miring di akhir. Contoh: https://bridge.ecampuz.com */
    'bridge_url' => rtrim((string) env('SSO_BRIDGE_URL', ''), '/'),

    /* Batas waktu menunggu Bridge (detik). Lebih dari ini -> "SSO tidak dapat dihubungi". */
    'timeout' => (int) env('SSO_TIMEOUT', 10),

    /* Endpoint Bridge v2 (relatif terhadap bridge_url), sesuai koleksi Postman. */
    'endpoints' => [
        'login' => '/api/v2/portal/login',
        'profile' => '/api/v2/profil',
        'logout' => '/api/v2/logout',
    ],

    /*
    | Jika profil SSO tidak berisi email, sistem membuat email pengganti:
    |   {nim atau nip}@{email_fallback_domain}
    | (kolom users.email wajib diisi & unik).
    */
    'email_fallback_domain' => env('SSO_EMAIL_FALLBACK_DOMAIN', 'sso.uinssc.ac.id'),

    /* Tautan "Lupa password?" di halaman login -> portal pusat SSO kampus (US-01). */
    'password_reset_url' => env('SSO_PASSWORD_RESET_URL', '#'),

    /*
    |--------------------------------------------------------------------------
    | Pemetaan respons /profil -> data user  (ASUMSI, silakan disesuaikan)
    |--------------------------------------------------------------------------
    |
    | Setiap field ditulis sebagai "dot path" (seperti data_get() Laravel), misalnya
    | 'fakultas.kode' artinya $respons['fakultas']['kode'].
    | Boleh berupa ARRAY beberapa kemungkinan: yang pertama berisi nilai yang dipakai.
    |
    | 'root' = letak objek profil di dalam respons. Banyak API Laravel membungkus
    | data di dalam "data": { "success": true, "data": { ...profil... } }.
    | Isi null jika profil langsung berada di akar respons.
    */
    'profile' => [

        'root' => 'data',

        'fields' => [
            'name' => ['nama', 'nama_lengkap', 'name'],
            'email' => ['email', 'email_kampus'],

            // Jenis akun di SSO (jika tidak ada di /profil, otomatis ditebak lewat username_patterns di bawah)
            'role' => ['jenis_user', 'role', 'level', 'tipe'],

            'nim' => ['nim'],
            'nip' => ['nip'],
            'nidn' => ['nidn'],

            // Status mahasiswa: di respons asli menggunakan key 'status' ("Aktif")
            'status' => ['status', 'status_mahasiswa'],
            'graduation_year' => ['tahun_lulus', 'tanggal_lulus', 'semester_lulus'],

            // Di respons asli Bridge UIN SSC, fakultas menggunakan key 'fakultas_nama' dan prodi menggunakan 'program_studi_nama'
            'faculty_code' => ['fakultas_nama', 'fakultas_kode', 'fakultas.kode', 'kode_fakultas'],
            'program_studi' => ['program_studi_nama', 'prodi.nama', 'program_studi', 'nama_prodi'],
        ],

        'role_values' => [
            'mahasiswa' => ['mahasiswa', 'mhs', 'student'],
            'dosen' => ['dosen', 'lecturer'],
        ],

        'graduated_status_values' => ['lulus', 'alumni', 'graduated'],

        'username_patterns' => [
            'dosen' => '/^([089]\d{9}|\d{18})$/',
            'mahasiswa' => '/^\d{8,12}$/',
        ],

        /*
        | Pemetaan 'fakultas_nama' dari Bridge UIN SSC ke kolom 'code' di tabel faculties database lokal.
        | Pastikan nilai di sebelah kanan ('FITK', 'FEBI', dst.) sesuai dengan kode fakultas di database Anda.
        */
        'faculty_code_map' => [
            'ILMU TARBIYAH DAN KEGURUAN' => 'FITK',
            'FAKULTAS ILMU TARBIYAH DAN KEGURUAN' => 'FITK',
            'EKONOMI DAN BISNIS ISLAM' => 'FEBI',
            'FAKULTAS EKONOMI DAN BISNIS ISLAM' => 'FEBI',
            'SYARIAH' => 'FASYA',
            'FAKULTAS SYARIAH' => 'FASYA',
            'USHULUDDIN DAN ADAB' => 'FUAD',
            'FAKULTAS USHULUDDIN DAN ADAB' => 'FUAD',
            'DAKWAH DAN KOMUNIKASI ISLAM' => 'FDKI',
            'FAKULTAS DAKWAH DAN KOMUNIKASI ISLAM' => 'FDKI',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pengguna simulasi (driver 'fake')
    |--------------------------------------------------------------------------
    |
    | Kunci = username yang diketik di form login (NIM / NIP / NIDN). Dosen juga bisa ditemukan
    | lewat field nip/nidn di profilnya, jadi satu dosen bisa login dengan NIP maupun NIDN.
    | 'response' meniru respons GET /api/v2/profil SESUAI pemetaan di atas, sehingga
    | jalur kode yang sama (SsoProfile::fromResponse) dipakai di mode fake maupun bridge.
    |
    | Mahasiswa 2210110001, dosen 0412038801, dan alumni 1910110045 = pelapor SSO di UserSeeder.
    | Alumni lulus 2019 (Dedi, NIM 1510110023) SENGAJA TIDAK ADA di sini, karena SSO memang tidak
    | punya data alumni <= 2021: ia login memakai NIM atau email + password hasil registrasi hidden link
    | (dicek di database lokal oleh LoginService, bukan oleh SSO).
    | Dosen NIP 197905152006042001 dan mahasiswa 2310210007 belum ada di database: untuk mencoba
    | "login SSO pertama kali" yang membuat akun otomatis.
    */
    'fake_users' => [

        '2210110001' => [ // Mahasiswa aktif FITK
            'password' => 'password',
            'response' => [
                'success' => true,
                'data' => [
                    'username' => '2210110001',
                    'nama' => 'Ahmad Mahasiswa',
                    'email' => 'mahasiswa@uinssc.test',
                    'jenis_user' => 'mahasiswa',
                    'nim' => '2210110001',
                    'status_mahasiswa' => 'Aktif',
                    'angkatan' => '2022',
                    'tahun_lulus' => null,
                    'prodi' => ['kode' => 'PAI', 'nama' => 'Pendidikan Agama Islam'],
                    'fakultas' => ['kode' => 'FITK', 'nama' => 'Fakultas Ilmu Tarbiyah dan Keguruan'],
                ],
            ],
        ],

        '0412038801' => [ // Dosen FITK: login dengan NIP 198804122015031001 ATAU NIDN 0412038801 (lihat FakeSsoClient::findUser). Tersimpan: nip + nidn
            'password' => 'password',
            'response' => [
                'success' => true,
                'data' => [
                    'username' => '0412038801',
                    'nama' => 'Dr. Budi Dosen',
                    'email' => 'dosen@uinssc.test',
                    'jenis_user' => 'dosen',
                    'nip' => '198804122015031001',
                    'nidn' => '0412038801',
                    'prodi' => ['kode' => 'PBA', 'nama' => 'Pendidikan Bahasa Arab'],
                    'fakultas' => ['kode' => 'FITK', 'nama' => 'Fakultas Ilmu Tarbiyah dan Keguruan'],
                ],
            ],
        ],

        '197905152006042001' => [ // Dosen FEBI yang login dengan NIP 18 digit (profil SSO tanpa NIDN)
            'password' => 'password',
            'response' => [
                'success' => true,
                'data' => [
                    'username' => '197905152006042001',
                    'nama' => 'Dra. Siti Dosen FEBI',
                    'email' => 'dosen.febi@uinssc.test',
                    'jenis_user' => 'dosen',
                    'nip' => '197905152006042001',
                    'nidn' => null, // -> hanya users.nip yang terisi; users.nidn tetap kosong
                    'prodi' => ['kode' => 'ES', 'nama' => 'Ekonomi Syariah'],
                    'fakultas' => ['kode' => 'FEBI', 'nama' => 'Fakultas Ekonomi dan Bisnis Islam'],
                ],
            ],
        ],

        '1910110045' => [ // Alumni lulus 2023 (>= 2022): data ADA di SSO
            'password' => 'password',
            'response' => [
                'success' => true,
                'data' => [
                    'username' => '1910110045',
                    'nama' => 'Citra Alumni SSO',
                    'email' => 'alumni2023@uinssc.test',
                    'jenis_user' => 'mahasiswa',
                    'nim' => '1910110045',
                    'status_mahasiswa' => 'Lulus',
                    'angkatan' => '2019',
                    'tahun_lulus' => '2023',
                    'prodi' => ['kode' => 'TMT', 'nama' => 'Tadris Matematika'],
                    'fakultas' => ['kode' => 'FITK', 'nama' => 'Fakultas Ilmu Tarbiyah dan Keguruan'],
                ],
            ],
        ],

        '2310210007' => [ // Mahasiswa FEBI yang belum pernah login (akun dibuat otomatis, email kosong)
            'password' => 'password',
            'response' => [
                'success' => true,
                'data' => [
                    'username' => '2310210007',
                    'nama' => 'Eka Mahasiswi FEBI',
                    'email' => null, // -> email pengganti 2310210007@{email_fallback_domain}
                    'jenis_user' => 'mahasiswa',
                    'nim' => '2310210007',
                    'status_mahasiswa' => 'Aktif',
                    'angkatan' => '2023',
                    'tahun_lulus' => null,
                    'prodi' => ['kode' => 'PBS', 'nama' => 'Perbankan Syariah'],
                    'fakultas' => ['kode' => 'FEBI', 'nama' => 'Fakultas Ekonomi dan Bisnis Islam'],
                ],
            ],
        ],
    ],

];
