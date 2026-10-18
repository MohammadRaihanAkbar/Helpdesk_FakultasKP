
# Login SSO Kampus (Bridge API v2)

Dokumen ini menjelaskan cara aplikasi memakai SSO kampus, **asumsi** yang dibuat karena contoh respons
profil belum tersedia, dan cara menyesuaikannya begitu contoh respons asli didapat.

## 1. Kapan SSO dipakai

Halaman login hanya punya satu kolom **NIM / NIDN / Email** (`App\Services\Auth\LoginService`):

| Isi kolom        | Jalur                                                    | Untuk                                                     |
| ---------------- | -------------------------------------------------------- | --------------------------------------------------------- |
| mengandung `@` | **login lokal** (tabel `users`, password bcrypt) | Staff, Admin Fakultas, Super Admin, Alumni lulus ≤ 2021  |
| selain itu       | **SSO kampus**                                     | Mahasiswa (NIM), Dosen (NIDN), Alumni lulus ≥ 2022 (NIM) |

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
| HTTP 401 / 422, atau tidak ada token                                                             | "NIM/NIDN atau password salah."                                                     |
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
| Email                  | `email`                  | `email`, `email_kampus` → kosong: `{nim/nidn}@{SSO_EMAIL_FALLBACK_DOMAIN}`                                                |
| Jenis akun             | (menentukan `user_type`) | `jenis_user`, `role`, `level`, `tipe` → dicocokkan dengan `profile.role_values`                                       |
| NIM                    | `nim`                    | `nim` → kosong: username yang diketik                                                                                         |
| NIDN                   | `nidn`                   | `nidn` → `nip` → username yang diketik                                                                                     |
| Status mahasiswa       | (aktif/lulus)              | `status_mahasiswa`, `status` → `lulus`/`alumni`/`graduated` berarti **alumni**                                  |
| Tahun lulus            | `graduation_year`        | `tahun_lulus`                                                                                                                  |
| Kode fakultas homebase | `origin_faculty_id`      | `fakultas.kode`, `kode_fakultas` → dicocokkan ke `faculties.code` (bisa diterjemahkan lewat `profile.faculty_code_map`) |
| Program studi          | `program_studi`          | `prodi.nama`, `program_studi`, `nama_prodi`                                                                                |

### Cara menentukan jenis pelapor (`user_type`)

1. Jika field jenis akun ada: nilainya dicari di `role_values` (`mahasiswa`/`mhs`/`student` → mahasiswa, `dosen`/`lecturer` → dosen).
   Nilai lain (mis. `pegawai`) **ditolak**.
2. Jika tidak ada: profil punya `nim` → mahasiswa; punya `nidn`/`nip` → dosen.
3. Jika tetap tidak jelas: ditebak dari **format username** (`profile.username_patterns`):
   10 digit diawali 0/8/9 atau 18 digit (NIP) → dosen; 8–12 digit lainnya → mahasiswa.
4. Mahasiswa dengan status lulus (atau tanpa status tapi punya tahun lulus) → **alumni** + `graduation_year`.

## 4. ⚠️ Catatan NIDN vs NIP

PRD menyebut dosen login dengan **NIDN**, sedangkan kolom `username` di Bridge tertulis **"NIM/NIP/NIK portal"**.
Aplikasi mengirim apa pun yang diketik ke Bridge apa adanya, lalu:

- menyimpan `nidn` dari profil jika ada, jika tidak memakai `nip`, jika tidak memakai username yang diketik;
- mencari akun dosen yang sudah ada berdasarkan nilai tersebut di kolom `users.nidn`.

**Perlu dikonfirmasi ke pengelola SSO:** apakah portal menerima NIDN sebagai username? Jika ternyata hanya NIP,
dosen harus mengetik NIP; agar akun tetap cocok, pastikan profil mengembalikan `nidn` (atau sepakati bahwa kolom
`nidn` berisi NIP, dan perbarui teks di halaman login).

## 5. Sinkronisasi ke tabel users (`SsoUserSynchronizer`)

Setiap login SSO:

- akun dicari berdasarkan `nim` (mahasiswa/alumni) atau `nidn` (dosen); jika belum ada → dibuat dengan `role = end_user`,
  `is_active = true`, dan **password acak** (password SSO tidak pernah disimpan);
- yang **selalu diperbarui**: nama, email, `user_type`, `graduation_year`, `program_studi`, `origin_faculty_id`
  (mahasiswa yang lulus otomatis menjadi alumni);
- yang **tidak pernah diubah**: `role` dan `faculty_id` (dosen yang ditunjuk menjadi Admin Fakultas tetap Admin);
- email dari SSO yang sudah dipakai akun lain tidak ditimpa: dipakai email lama / email pengganti;
- akun yang dinonaktifkan (`is_active = false`) tetap ditolak walau password SSO benar.

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
