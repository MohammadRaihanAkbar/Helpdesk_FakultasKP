
# Login SSO Kampus (Bridge API v2)

Dokumen ini menjelaskan cara aplikasi memakai SSO kampus, **asumsi** yang dibuat karena contoh respons
profil belum tersedia, dan cara menyesuaikannya begitu contoh respons asli didapat.

## 1. Kapan SSO dipakai

Halaman login hanya punya satu kolom **NIM / NIP / Email** (`App\Services\Auth\LoginService`):

| Isi kolom        | Jalur                                                                                                    | Untuk                                                            |
| ---------------- | -------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| mengandung `@` | **login lokal** dengan email (tabel `users`, password bcrypt)                                    | Staff, Admin Fakultas, Super Admin, Alumni lulus ≤ 2021         |
| selain itu       | 1) **login lokal dengan NIM** bila NIM itu milik alumni hidden link (lulus ≤ 2021) dan password cocok | Alumni lulus ≤ 2021                                             |
|                  | 2) kalau tidak → **SSO kampus**                                                                     | Mahasiswa (NIM), Dosen (NIP; NIDN juga diterima), Alumni lulus ≥ 2022 (NIM) |

Langkah 1) ditambahkan saat perbaikan bug M1 (lihat `docs/progres/M1-perbaikan-bug.md`): alumni ≤ 2021 tidak ada
di SSO, jadi dulu login dengan NIM selalu gagal. Langkah ini hanya berlaku untuk akun `user_type = alumni` dengan
`graduation_year ≤ 2021` (scope `User::hiddenLinkAlumni()`); akun hasil sinkron SSO tetap wajib lewat SSO.

## 2. Endpoint Bridge yang dipakai

Sumber: `docs/bridge-api-v2.postman.json` (koleksi Postman dari kampus).

| Langkah        | Request                                            | Catatan                                                                                   |
| -------------- | -------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| 1. Login       | `POST {SSO_BRIDGE_URL}/api/v2/portal/login`      | body *form-urlencoded*: `username`, `password`; header `Accept: application/json` |
| 2. Ambil token | dari respons login: `data.token`, atau `token` | sama persis dengan script test di Postman                                                 |
| 3. Profil      | `GET {SSO_BRIDGE_URL}/api/v2/profil`             | header `Authorization: Bearer {token}`                                                  |
| 4. Logout      | `POST {SSO_BRIDGE_URL}/api/v2/logout`            | dipanggil saat user logout; *best effort* (gagal pun tidak masalah)                     |

Penanganan error (`App\Services\Sso\BridgeSsoClient`):

| Kondisi                                                                                          | Pesan ke pengguna                                                                   |
| ------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------- |
| HTTP 401 / 422, atau tidak ada token                                                             | "NIM/NIP atau password salah. Gunakan password SSO kampus; alumni lulusan 2021 ke bawah memakai password yang dibuat saat registrasi." (`LoginService::IDENTITY_FAILED_MESSAGE`) |
| Timeout (> `SSO_TIMEOUT` detik, bawaan 10), gagal koneksi, HTTP 5xx, `SSO_BRIDGE_URL` kosong | "Layanan SSO kampus sedang tidak dapat dihubungi. Silakan coba beberapa saat lagi." |
| Profil berhasil diambil tapi akunnya bukan mahasiswa/dosen (mis. tenaga kependidikan)            | "Akun SSO Anda tidak terdaftar sebagai mahasiswa, alumni, atau dosen. …"           |
| Profil tidak bisa dibaca (bentuknya tidak sesuai pemetaan)                                       | "Data profil dari SSO kampus tidak dapat dibaca. …"                                |

## 3. ⚠️ Asumsi bentuk respons `/api/v2/profil`

Koleksi Postman **tidak berisi contoh respons**. Aplikasi saat ini mengasumsikan bentuk seperti ini
(dipakai juga oleh akun simulasi di `config/sso.php` → `fake_users`):

```json
{
  "success": true,
  "data": {
    "username": "1910110045",
    "nama": "Citra Alumni SSO",
    "email": "alumni2023@uinssc.test",
    "jenis_user": "mahasiswa",
    "nim": "1910110045",
    "status_mahasiswa": "Lulus",
    "angkatan": "2019",
    "tahun_lulus": "2023",
    "prodi": { "kode": "TMT", "nama": "Tadris Matematika" },
    "fakultas": { "kode": "FITK", "nama": "Fakultas Ilmu Tarbiyah dan Keguruan" }
  }
}
```

Contoh dosen: `"jenis_user": "dosen"`, `"nip": "198804122015031001"`, `"nidn": "0412038801"`, tanpa `nim`/`status_mahasiswa`.

### Pemetaan field (config/sso.php → `profile.fields`)

Setiap field boleh punya beberapa kemungkinan nama; yang pertama terisi dipakai. Titik (`.`) berarti objek bersarang.

| Data yang dibutuhkan   | Kolom `users`            | Field yang dicoba (urut)                                                                                                         |
| ---------------------- | -------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| Letak objek profil     | —                         | `profile.root` = `data` (isi `null` jika profil di akar respons)                                                           |
| Nama                   | `name`                   | `nama`, `nama_lengkap`, `name`                                                                                             |
| Email                  | `email`                  | `email`, `email_kampus` → kosong: `{nim/nip}@{SSO_EMAIL_FALLBACK_DOMAIN}`                                                |
| Jenis akun             | (menentukan `user_type`) | `jenis_user`, `role`, `level`, `tipe` → dicocokkan dengan `profile.role_values`                                       |
| NIM                    | `nim`                    | `nim` → kosong: username yang diketik                                                                                         |
| NIP (dosen, M5)        | `nip`                    | `nip` → kosong: username yang diketik (kecuali yang diketik = NIDN dari profil)                                            |
| NIDN (dosen)           | `nidn`                   | `nidn` → kosong: **dibiarkan kosong** (sejak M5 tidak lagi diisi NIP)                                                      |
| Status mahasiswa       | (aktif/lulus)              | `status_mahasiswa`, `status` → `lulus`/`alumni`/`graduated` berarti **alumni**                                  |
| Tahun lulus            | `graduation_year`        | `tahun_lulus`                                                                                                                  |
| Kode fakultas homebase | `origin_faculty_id`      | `fakultas.kode`, `kode_fakultas` → dicocokkan ke `faculties.code` (bisa diterjemahkan lewat `profile.faculty_code_map`) |
| Program studi          | `program_studi`          | `prodi.nama`, `program_studi`, `nama_prodi`                                                                                |

### Cara menentukan jenis pelapor (`user_type`)

1. Jika field jenis akun ada: nilainya dicari di `role_values` (`mahasiswa`/`mhs`/`student` → mahasiswa, `dosen`/`lecturer` → dosen).
   Nilai lain (mis. `pegawai`) **ditolak**.
2. Jika tidak ada: profil punya `nim` → mahasiswa; punya `nip`/`nidn` → dosen.
3. Jika tetap tidak jelas: ditebak dari **format username** (`profile.username_patterns`):
   10 digit diawali 0/8/9 atau 18 digit (NIP) → dosen; 8–12 digit lainnya → mahasiswa.
4. Mahasiswa dengan status lulus (atau tanpa status tapi punya tahun lulus) → **alumni** + `graduation_year`.

## 4. NIP untuk dosen (keputusan M5)

PRD menyebut dosen login dengan **NIDN**, sedangkan koleksi Postman Bridge menulis field `username` sebagai
**"NIM/NIP/NIK portal"** dan variabel `{{nip}}` = "NIP dosen". **NIDN tidak disebut sama sekali.**
Keputusannya: **dosen diidentifikasi dengan NIP**. Sampai M4 kolom `users.nidn` ikut menyimpan NIP (namanya
menyesatkan); sejak M5 ada kolom sendiri, `users.nip` (migration `2026_10_21_100000_add_nip_to_users_table`).

- apa pun yang diketik (NIP 18 digit atau NIDN 10 digit) tetap dikirim ke Bridge apa adanya, jadi NIDN masih bisa dipakai;
- `users.nip` diisi **NIP dari profil**; kalau profil tidak punya field `nip`, yang diketik dianggap NIP;
- `users.nidn` diisi **hanya** kalau profil SSO memang mengirim `nidn`;
- akun dosen lama dicari dengan **semua** nomor yang diketahui (NIP, NIDN, yang diketik —
  `SsoProfile::identifierCandidates()`) di kolom `nip` **dan** `nidn`, jadi login dengan NIP lalu dengan NIDN tetap
  masuk ke **akun yang sama**; NIP lama yang masih tersimpan di kolom `nidn` dipindah ke `nip` saat login;
- migration M5 langsung memindahkan nilai 18 digit di kolom `nidn` (NIP PNS) ke kolom `nip`;
- mode `fake`: dosen "Dr. Budi Dosen" bisa login dengan NIP `198804122015031001` **atau** NIDN `0412038801`
  (tersimpan: `nip` + `nidn`); dosen "Dra. Siti Dosen FEBI" (profil tanpa NIDN) login dengan NIP `197905152006042001`
  dan hanya kolom `nip` yang terisi.

**Masih perlu dikonfirmasi ke pengelola SSO:** apakah respons `/api/v2/profil` dosen benar memuat field `nip`
(dan `nidn`). Kalau namanya lain, cukup ubah `profile.fields.nip` di `config/sso.php`.

## 5. Sinkronisasi ke tabel users (`SsoUserSynchronizer`)

Setiap login SSO:

- akun dicari berdasarkan `nim` (mahasiswa/alumni) atau `nip` (dosen; juga dicocokkan dengan NIDN); jika belum ada → dibuat dengan `role = end_user`,
  `is_active = true`, dan **password acak** (password SSO tidak pernah disimpan);
- yang **selalu diperbarui**: nama, email, `user_type`, `graduation_year`, `program_studi`, `origin_faculty_id`
  (mahasiswa yang lulus otomatis menjadi alumni);
- yang **tidak pernah diubah**: `role` dan `faculty_id` (dosen yang ditunjuk menjadi Admin Fakultas tetap Admin);
- email dari SSO yang sudah dipakai akun lain tidak ditimpa: dipakai email lama / email pengganti;
- akun yang dinonaktifkan (`is_active = false`) tetap ditolak walau password SSO benar;
- jika akun yang ditemukan adalah akun **alumni hidden link** (NIM-nya ternyata milik pengguna SSO yang sah),
  password lokalnya diganti acak, supaya pendaftar hidden link tidak bisa lagi masuk ke akun tersebut.

Token Bridge disimpan di session (`sso_token`) untuk dipakai saat logout.

## 6. Menyesuaikan saat contoh respons asli sudah ada

1. Login sekali ke Bridge (Postman) dan simpan respons `GET /api/v2/profil` untuk **satu mahasiswa aktif, satu alumni
   lulus ≥ 2022, dan satu dosen** (samarkan data pribadi).
2. Bandingkan dengan tabel di bagian 3, lalu ubah `config/sso.php` → `profile` (root, nama field, `role_values`,
   `graduated_status_values`, `faculty_code_map`). Tidak perlu mengubah kode PHP.
3. Ganti isi `fake_users.*.response` dengan bentuk asli (data disamarkan) supaya mode `fake` dan test meniru Bridge.
4. Jalankan `php artisan test`.
5. Coba mode bridge: `SSO_DRIVER=bridge`, `SSO_BRIDGE_URL=https://…`, lalu `php artisan config:clear`.

## 7. File terkait

| File                                                             | Peran                                                 |
| ---------------------------------------------------------------- | ----------------------------------------------------- |
| `config/sso.php`                                               | driver, URL, timeout, pemetaan field, akun simulasi   |
| `app/Services/Sso/SsoClient.php`                               | interface (kontrak) `authenticate()` & `logout()` |
| `app/Services/Sso/BridgeSsoClient.php`                         | implementasi HTTP ke Bridge v2                        |
| `app/Services/Sso/FakeSsoClient.php`                           | implementasi simulasi dari `fake_users`             |
| `app/Services/Sso/SsoProfile.php`                              | DTO + pemetaan respons → data user                   |
| `app/Services/Sso/SsoUserSynchronizer.php`                     | buat/perbarui baris `users`                         |
| `app/Services/Sso/SsoException.php`                            | error dengan pesan ramah pengguna                     |
| `app/Providers/AppServiceProvider.php`                         | memilih implementasi sesuai `SSO_DRIVER`            |
| `tests/Feature/Auth/SsoLoginTest.php`, `tests/Feature/Sso/*` | test (Bridge dipalsukan dengan `Http::fake()`)      |
