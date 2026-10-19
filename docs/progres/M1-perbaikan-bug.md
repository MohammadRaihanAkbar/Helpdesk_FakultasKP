
# Catatan Belajar M1: Perbaikan Bug Login

Catatan ini lanjutan dari `fondasi.md` dan `M1-tampilan.md`. Isinya bug yang ditemukan setelah Milestone 1
"selesai", kenapa bisa terjadi, bagaimana memperbaikinya, dan cara mengeceknya sendiri di Laragon.
Fitur tiket belum disentuh di sini.

Ringkasan:

| No | Bug                                                                                   | File utama yang diubah                                              |
| -- | ------------------------------------------------------------------------------------- | ------------------------------------------------------------------- |
| 1  | Alumni ≤ 2021 sudah registrasi, tapi login dengan NIM ditolak "NIM/NIDN atau password salah" | `app/Services/Auth/LoginService.php`, `app/Models/User.php`     |
| 2  | Celah: pendaftar hidden link bisa "menduduki" akun milik NIM pengguna SSO              | `app/Services/Sso/SsoUserSynchronizer.php`                        |
| 3  | Dosen hanya bisa login dengan NIDN, padahal Bridge menulis "NIM/NIP/NIK"                | `SsoProfile.php`, `FakeSsoClient.php`, `SsoUserSynchronizer.php`, `config/sso.php` |
| 4  | Setelah beberapa kali login dari satu IP, form registrasi alumni ikut ditolak 429       | `app/Providers/AppServiceProvider.php`, `routes/web.php`          |
| 5  | Sisa kode bawaan `make:view` di atas halaman registrasi alumni                         | `resources/views/auth/register-alumni.blade.php`                  |

---

## 1. Bug utama: alumni sudah terdaftar tapi tidak bisa login

### Gejala

1. Alumni lulus 2019 membuka `/register/alumni`, mengisi form, klik **Daftar** → muncul "Registrasi alumni berhasil".
2. Di HeidiSQL, baris barunya **sudah ada** di tabel `users` (`user_type = alumni`, `graduation_year = 2019`).
3. Di halaman login ia mengetik **NIM** dan password tadi → ditolak: **"NIM/NIDN atau password salah."**

### Kenapa terjadi?

Kolom login cuma satu. `LoginService` dulu memilih jalur hanya dari ada/tidaknya tanda `@`:

```php
// SEBELUM
$user = self::isLocalLogin($login)               // ada "@"?
    ? $this->authenticateLocal($login, $password) // ya    -> cek tabel users
    : $this->authenticateSso($login, $password);  // tidak -> kirim ke SSO kampus
```

NIM tidak punya `@`, jadi **selalu dikirim ke SSO**. Padahal alumni lulus ≤ 2021 memang **tidak ada di SSO**
(itulah alasan ada hidden link). SSO menjawab "tidak dikenal", lalu muncul pesan "NIM/NIDN atau password salah".
Datanya benar, password-nya benar, hanya saja **tempat mengeceknya salah**.

Login dengan **email** sebenarnya berhasil, tapi itu tidak jelas bagi pengguna: kolomnya berlabel
"NIM / NIDN / Email", dan wajar kalau alumni mengetik NIM-nya. Bahkan ada test lama
(`test_alumni_before_2022_is_not_in_sso`) yang justru *mengharapkan* error ini, jadi bug-nya "terkunci" oleh test.

Kemungkinan penyebab lain juga dicek, dan **bukan** penyebabnya:

| Dugaan                              | Hasil cek                                                                                         |
| ----------------------------------- | ------------------------------------------------------------------------------------------------- |
| Password di-hash dua kali           | Tidak. Controller mengirim password asli; cast `'hashed'` di model meng-hash **sekali**. Ada test `test_password_is_hashed_exactly_once`. |
| Email huruf besar/kecil             | Aman. Registrasi menyimpan huruf kecil, login juga mengecilkan input (ada test-nya).               |
| `is_active` salah                   | Tidak. Registrasi mengisi `is_active = true`.                                                     |
| Kunci `ALUMNI_REGISTRATION_KEY`     | Tidak memengaruhi login; hanya menjaga halaman registrasi. Alur daftar → login dengan kunci juga dites. |

### Cara memperbaikinya

Sebelum dikirim ke SSO, NIM dicek dulu ke database: apakah ini **alumni hidden link**?

```php
// SESUDAH
$user = self::isLocalLogin($login)
    ? $this->authenticateLocal($login, $password)
    : ($this->authenticateHiddenLinkAlumni($login, $password)   // 1) alumni <= 2021? cek lokal
        ?? $this->authenticateSso($login, $password));          // 2) bukan / tidak cocok -> SSO
```

`??` artinya "kalau hasil kiri `null`, pakai yang kanan". `authenticateHiddenLinkAlumni()` mengembalikan
`null` bila NIM bukan milik alumni hidden link atau password-nya tidak cocok, sehingga login lanjut ke SSO
seperti biasa.

"Alumni hidden link" didefinisikan di **satu tempat**, `app/Models/User.php`:

```php
#[Scope]
protected function hiddenLinkAlumni(Builder $query): void
{
    $query->where('user_type', UserType::Alumni)
        ->whereNotNull('graduation_year')
        ->where('graduation_year', '<=', AlumniRegistrationRequest::LAST_GRADUATION_YEAR); // 2021
}
```

Lalu dipakai: `User::hiddenLinkAlumni()->where('nim', $nim)->first()`.

**Kenapa tidak cek password lokal untuk SEMUA NIM saja?** Karena mahasiswa, dosen, dan alumni ≥ 2022 harus
lewat SSO (PRD US-01). Kalau semua NIM boleh login lokal, akun yang password lokalnya diketahui (mis. akun demo
seeder) bisa melewati SSO. Dengan syarat di atas, hanya alumni ≤ 2021 yang memakai password lokal. Akun hasil
sinkron SSO tidak lolos syarat itu, dan seandainya pun lolos, password lokalnya string acak 40 karakter.

Pesan yang ikut diperjelas:

| Tempat                         | Sekarang                                                                                                  |
| ------------------------------ | --------------------------------------------------------------------------------------------------------- |
| Error login NIM/NIDN/NIP       | "NIM/NIDN/NIP atau password salah. Gunakan password SSO kampus; alumni lulusan 2021 ke bawah memakai password yang dibuat saat registrasi." |
| Petunjuk di bawah kolom login  | menyebut alumni ≤ 2021: NIM lama atau email; dosen: NIDN atau NIP                                         |
| Pesan sukses registrasi        | "… Silakan login dengan NIM lama atau email pribadi Anda, beserta password yang baru dibuat."           |
| Form registrasi                | petunjuk di kolom NIM ("bisa dipakai untuk login") dan password ("bukan password SSO")                    |

Pesan error sengaja **tidak** memberi tahu "NIM ini terdaftar tapi password salah", supaya orang lain tidak bisa
menebak NIM siapa saja yang sudah punya akun.

> Catatan PRD: US-01B hanya menulis alumni login dengan *email*. Login dengan NIM adalah **tambahan**, bukan
> pengganti; email tetap bisa dipakai.

---

## 2. Celah keamanan: akun alumni palsu yang NIM-nya milik orang lain

Ditemukan saat memeriksa bug 1. Skenario (sudah ada sejak awal, tidak terkait perbaikan di atas):

1. Penyusup mendaftar lewat hidden link memakai **NIM mahasiswa aktif** yang belum pernah login ke helpdesk,
   dengan email & password miliknya sendiri.
2. Mahasiswa pemilik NIM login lewat SSO. `SsoUserSynchronizer` menemukan akun dengan NIM itu, lalu
   memperbaruinya (nama, email kampus, `user_type = mahasiswa`), **tetapi password lama tetap**.
3. Penyusup masih bisa login dengan email kampus mahasiswa itu + password miliknya → melihat tiket mahasiswa tersebut.

Perbaikan di `SsoUserSynchronizer::sync()`:

```php
if ($user->exists && $user->isHiddenLinkAlumni()) {
    $user->password = Str::random(40); // password hidden link dibuang
}
```

Pemilik NIM yang sah tetap login lewat SSO (password SSO tidak pernah disimpan). Test:
`test_hidden_link_registrant_loses_access_once_the_real_sso_owner_logs_in`.

---

## 3. Dosen: NIP atau NIDN?

### Masalah

- PRD: dosen login dengan **NIDN** (10 digit, mis. `0412038801`).
- Koleksi Postman Bridge (`docs/bridge-api-v2.postman.json`): field `username` = **"NIM/NIP/NIK portal"**,
  variabel `{{nip}}` = "NIP dosen". **NIDN tidak disebut sama sekali.** NIP PNS panjangnya 18 digit
  (mis. `198804122015031001`).

Kemungkinan besar portal kampus memakai **NIP**. Dulu mode `fake` hanya mengenal NIDN, dan kalau dosen login
dengan NIP lalu dengan NIDN, bisa tercipta **dua akun** untuk orang yang sama.

### Cara memperbaikinya

1. `SsoProfile` sekarang menyimpan `nip` terpisah dan punya `identifierCandidates()`: daftar semua nomor
   milik dosen itu (NIDN, NIP, dan yang diketik).
2. `SsoUserSynchronizer` mencari akun lama dengan **semua** nomor itu, lalu menyimpan nomor utama ke `users.nidn`:
   NIDN kalau SSO memberikannya, kalau tidak NIP. Hasilnya **satu dosen = satu akun**, apa pun yang ia ketik.
3. `FakeSsoClient` mencari pengguna simulasi juga lewat field `nidn`/`nip` di profilnya, jadi Dr. Budi Dosen bisa
   login dengan NIDN maupun NIP.
4. Akun simulasi baru: **Dra. Siti Dosen FEBI**, NIP `197905152006042001`, profil tanpa NIDN → tersimpan dengan NIP.

Kolom `users.nidn` adalah `string(20)`, cukup untuk NIP 18 digit, jadi **skema ERD tidak diubah**.

> ⚠️ **Perlu dikonfirmasi ke pembimbing / pengelola SSO**: (1) apakah portal menerima NIDN atau hanya NIP;
> (2) apakah respons `/api/v2/profil` dosen memuat `nidn`. Kalau tidak ada `nidn`, kolom `users.nidn` akan
> berisi NIP. Detail di `docs/SSO.md` bagian 4.

---

## 4. Rate limit login dan registrasi tercampur (error 429)

### Gejala

Ditemukan saat uji jalan: setelah 18 akun demo login bergantian dari laptop yang sama, membuka form
registrasi alumni lalu klik **Daftar** langsung muncul **"429 Too Many Requests"** (halaman bahasa Inggris).

### Kenapa terjadi?

Route memakai throttle tanpa nama: `throttle:20,1` (login) dan `throttle:6,1` (registrasi). Throttle tanpa nama
menghitung **per IP untuk semua route sekaligus**. Jadi 7 kali login sudah menghabiskan jatah registrasi (6).
Di kampus ini lebih parah: ratusan mahasiswa di Wi-Fi kampus bisa keluar dengan **IP publik yang sama**, sehingga
batas 20 login/menit per IP bisa memblokir banyak orang sekaligus.

### Cara memperbaikinya

Pakai **limiter bernama** di `AppServiceProvider::configureRateLimiting()`; tiap nama punya hitungan sendiri:

```php
RateLimiter::for('login', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
RateLimiter::for('alumni-registration', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
```

Di `routes/web.php`: `->middleware('throttle:login')` dan `->middleware('throttle:alumni-registration')`.
Angka 60/menit mengikuti PRD 6.3. Perlindungan utama dari tebak password tetap **5x gagal/menit per
login + IP** di `LoginRequest`. Ditambah halaman `resources/views/errors/429.blade.php` berbahasa Indonesia.

---

## 5. Sisa kode bawaan di halaman registrasi

Baris pertama `register-alumni.blade.php` berisi `<div><!-- Smile, breathe, and go slowly. --></div>`, sisa
perintah `php artisan make:view`. Div ini tercetak **sebelum** `<!DOCTYPE html>`, sehingga HTML-nya tidak valid.
Sudah dihapus.

---

## 6. Hasil audit Milestone 1 (yang sudah benar, tidak diubah)

| Aturan PRD                                               | Status                                                                   |
| -------------------------------------------------------- | ------------------------------------------------------------------------ |
| bcrypt cost ≥ 12 (6.3)                                  | ✅ `BCRYPT_ROUNDS=12` di `.env`; hash akun seeder ber-cost 12          |
| Rate limit login                                         | ✅ 5x gagal/menit per login+IP, + 60/menit per IP (setelah perbaikan 4)  |
| Sesi 7 hari (US-01, F-AUTH-05)                           | ✅ `SESSION_LIFETIME=10080`, cookie "ingat saya" 10080 menit            |
| CSRF                                                     | ✅ semua form POST memakai `@csrf`                                      |
| Regenerasi session saat login, invalidasi saat logout    | ✅                                                                       |
| ERD 12 tabel + UNIQUE (`email`, `nim`, `nidn`, `faculties.code/slug`, `(faculty_id,name)`, `(category_id,user_type)`, `(staff_id,category_id)`, `ticket_number`, `ratings.ticket_id`) | ✅ |
| Seeder 6 fakultas × 12 kategori = 72                     | ✅                                                                       |
| Registrasi: tahun ≥ 2022 / NIM ganda → pesan `ALUMNI_USE_SSO` | ✅                                                                  |
| Tidak ada tautan publik ke registrasi alumni             | ✅                                                                       |

---

## 7. Cara mengecek sendiri di Laragon

Buka **Terminal** Laragon di folder proyek.

```bash
# 1. Ambil perubahan terbaru, lalu pasang ulang dependency (aman diulang)
composer install
npm install
npm run build

# 2. Jalankan semua test (SQLite in-memory, database Laragon tidak tersentuh)
php artisan test
#    hanya test yang berhubungan dengan bug ini:
php artisan test --filter="AlumniLoginTest|DosenLoginTest|RateLimitTest"

# 3. Isi ulang database demo (MENGHAPUS semua data lama di database helpdesk_fakultas!)
php artisan migrate:fresh --seed

# 4. Jalankan aplikasi
php artisan serve
```

Lalu buka `http://localhost:8000` dan coba manual:

1. **Alumni demo**: login dengan NIM `1510110023` / `password` → masuk dashboard. Logout, ulangi dengan
   `dedi.alumni2019@example.com` / `password` → juga masuk.
2. **Alumni baru**: buka `http://localhost:8000/register/alumni` (tambahkan `?key=...` kalau
   `ALUMNI_REGISTRATION_KEY` di `.env` diisi). Daftar dengan tahun lulus mis. 2018. Setelah diarahkan ke login,
   masuk dengan **NIM** yang tadi didaftarkan, lalu coba juga dengan **email**-nya.
3. **Password salah**: NIM yang sama + password asal → muncul pesan yang menyebut kedua jenis password.
4. **Dosen**: `0412038801` / `password` (NIDN) dan `198804122015031001` / `password` (NIP) → keduanya masuk ke
   akun yang sama. Cek di HeidiSQL: hanya ada **satu** baris Dr. Budi Dosen di tabel `users`.
5. **Dosen NIP saja**: `197905152006042001` / `password` → akun baru "Dra. Siti Dosen FEBI", kolom `nidn` berisi NIP.

Kalau masih ditolak padahal yakin benar: pastikan `.env` berisi `SSO_DRIVER=fake`, jalankan
`php artisan config:clear`, dan tunggu satu menit bila sebelumnya sudah 5 kali salah (kena batas percobaan).
