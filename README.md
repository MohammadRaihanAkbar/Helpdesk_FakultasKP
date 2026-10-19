
# Helpdesk Fakultas Terpadu — UIN SSC

Aplikasi helpdesk (layanan aduan & permohonan informasi) untuk **seluruh fakultas UIN SSC**
(FITK, FUA, FDKI, FEBI, FASYA, FASTIH) dalam satu aplikasi.

- **Pelapor** (mahasiswa, dosen, alumni) membuat tiket ke fakultas tujuan, memantau statusnya, berdiskusi dengan staff, dan memberi rating.
- **Staff Fakultas** mengambil tiket dari antrean sesuai spesialisasi layanannya dan menyelesaikannya (dengan bukti).
- **Admin Fakultas** mengelola staff, spesialisasi, katalog layanan & target SLA, memantau antrean, dan melihat laporan.
- **Super Admin** mengelola data master fakultas dan menunjuk Admin Fakultas.

Login mahasiswa/dosen/alumni ≥ 2022 memakai **SSO kampus** (NIM, atau NIDN/NIP untuk dosen). Staff dan admin memakai email + password.
Alumni lulusan ≤ 2021 (daftar lewat hidden link) memakai **NIM lama atau email** + password yang dibuat saat registrasi.

Dibangun dengan **Laravel 13 + Blade + MySQL**, Alpine.js, Tailwind CSS v4 (Vite). Dokumen acuan: PRD v6.2 dan Rencana Teknis.

> Status: **Milestone 2 (kategori & tiket)** — selain fondasi M1 (login lokal + SSO, registrasi alumni, middleware
> peran, tema UIN), sekarang Admin Fakultas bisa mengatur layanan & staff, dan pelapor bisa membuat tiket,
> memantau statusnya, dan berdiskusi di thread. Fitur Staff (antrean, ambil tiket, ubah status) menyusul di M3.

## Fitur yang sudah bisa dipakai

| Peran          | Halaman                                   | Isi                                                                                       |
| -------------- | ----------------------------------------- | ----------------------------------------------------------------------------------------- |
| Pelapor        | `/dashboard`                              | 4 kartu (Total, Menunggu, Diproses, Selesai), tombol Buat Tiket, 5 tiket terbaru          |
| Pelapor        | `/tickets/new`                            | Fakultas → Kelompok Layanan → Jenis Masalah (sesuai jenis pelapor), judul, deskripsi, lampiran JPG/PNG/PDF (≤ 5 MB, ≤ 3) |
| Pelapor        | `/tickets`, `/tickets/{id}`               | Tiket Saya (Menunggu/Diproses), detail, linimasa, thread diskusi maks. 20 pesan            |
| Pelapor        | `/history`, `/history/{id}`               | Riwayat (Selesai/Ditolak), alasan penolakan, bukti penyelesaian, **Buat Ulang Tiket** bila salah fakultas/kategori |
| Admin Fakultas | `/admin/services`                         | Pohon kategori, ubah SLA/deskripsi/On-Off, kategori tambahan (tambah, ubah, hapus)         |
| Admin Fakultas | `/admin/staff`, `/admin/staff/{id}/specialties` | Buat akun staff, aktif/nonaktif, spesialisasi per Kelompok Layanan, ringkasan "Kelompok tanpa Staff" |
| Staff          | `/staff/dashboard`                        | Profil + daftar spesialisasi (antrean di M3)                                              |
| Super Admin    | `/super-admin/dashboard`                  | Dashboard kosong (M5)                                                                     |

Lampiran disimpan di `storage/app/private` (tidak bisa dibuka lewat URL publik) dan hanya bisa diunduh
lewat aplikasi oleh orang yang berhak. Tidak perlu `php artisan storage:link`.

---

## Kebutuhan

- Windows + [Laragon](https://laragon.org/) (Full) dengan **PHP ≥ 8.3** dan **MySQL/MariaDB**
- [Composer](https://getcomposer.org/) (sudah ada di Laragon)
- [Node.js](https://nodejs.org/) ≥ 20 (untuk Vite)
- Git

Ekstensi PHP yang dipakai: `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `bcmath`, `curl`, `xml`
(untuk menjalankan test juga `pdo_sqlite`). Di Laragon cek lewat menu **PHP → Extensions**.

## Cara setup di Laragon

Buka **Terminal** Laragon (tombol *Terminal*), lalu:

```bash
# 1. Ambil kode ke folder www Laragon
cd C:\laragon\www
git clone <url-repo> helpdesk-fakultas
cd helpdesk-fakultas

# 2. Pasang dependency PHP & JavaScript
composer install
npm install

# 3. Buat file .env dari contoh, lalu buat APP_KEY
copy .env.example .env
php artisan key:generate
```

4. **Buat database** `helpdesk_fakultas` (Laragon → *Database* / HeidiSQL → klik kanan → *Create new → Database*),
   atau lewat terminal:

   ```bash
   mysql -u root -e "CREATE DATABASE helpdesk_fakultas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   ```

   Pengaturan bawaan `.env` sudah cocok untuk Laragon (`DB_USERNAME=root`, password kosong).
5. **Buat tabel + isi data contoh**:

   ```bash
   php artisan migrate:fresh --seed
   ```

   Hasilnya: 6 fakultas, 75 kategori layanan (12 standar per fakultas + 3 kategori tambahan contoh di FITK),
   akun demo, dan 8 tiket contoh (lengkap dengan lampiran, thread, bukti, dan rating).
   Perintah ini **menghapus semua tabel** lalu membuat ulang, jadi hanya untuk development.
6. **Jalankan aplikasi** (dua terminal):

   ```bash
   npm run dev          # terminal 1: Vite (CSS/JS, auto-refresh)
   php artisan serve    # terminal 2: server Laravel -> http://localhost:8000
   ```

   Atau pakai virtual host Laragon: klik kanan Laragon → *Reload* (atau *Apache/Nginx → Reload*), lalu buka
   `http://helpdesk-fakultas.test`. Ubah `APP_URL` di `.env` menjadi alamat tersebut. `npm run dev` tetap perlu dijalankan
   (atau `npm run build` sekali jika tidak sedang mengubah tampilan).

## Akun demo

Semua password: **`password`**. Login di `/login` (satu kolom untuk NIM / NIDN / Email).

| Peran                   | Login                               | Keterangan                                                                                                 |
| ----------------------- | ----------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Super Admin             | `superadmin@uinssc.test`          |                                                                                                            |
| Admin Fakultas          | `admin.fitk@uinssc.test`          | pola sama: `admin.fua@…`, `admin.fdki@…`, `admin.febi@…`, `admin.fasya@…`, `admin.fastih@…` |
| Staff FITK              | `staff.akademik.fitk@uinssc.test` | Sistem Akademik, Jadwal Mengajar                                                                           |
| Staff FITK              | `staff.umum.fitk@uinssc.test`     | Administrasi, UKT, Fasilitas, IT, Laboratorium Microteaching (kategori tambahan)                           |
| Staff FITK              | `staff.alumni.fitk@uinssc.test`   | Transkrip, PDDIKTI                                                                                         |
| Staff FITK              | `staff.dekanat.fitk@uinssc.test`  | Integritas Dosen (kategori sensitif)                                                                       |
| Staff FITK (nonaktif)   | `staff.cuti.fitk@uinssc.test`     | akun dinonaktifkan: login ditolak (contoh fitur aktif/nonaktif)                                            |
| Staff FEBI              | `staff.umum.febi@uinssc.test`     | untuk mencoba bahwa Admin FITK tidak bisa menyentuh staff FEBI                                             |
| Mahasiswa (SSO)         | NIM `2210110001`                  | punya 5 tiket demo: menunggu, diproses (ada thread), selesai, ditolak "salah fakultas/kategori", sensitif |
| Dosen (SSO)             | NIDN `0412038801`                 | bisa juga dengan NIP `198804122015031001` (akun yang sama)                                                 |
| Dosen FEBI (SSO)        | NIP `197905152006042001`          | profil SSO tanpa NIDN: tersimpan dengan NIP; akun dibuat otomatis saat login pertama                       |
| Alumni lulus 2023 (SSO) | NIM `1910110045`                  | jenis pelapor otomatis *alumni*                                                                          |
| Alumni lulus 2019       | NIM `1510110023` atau `dedi.alumni2019@example.com` | tidak ada di SSO, daftar lewat hidden link; dicek di database lokal                  |
| Mahasiswa baru (SSO)    | NIM `2310210007`                  | belum ada di database: akun dibuat otomatis saat login pertama                                             |

Akun SSO di atas hanya bisa dipakai pada mode `SSO_DRIVER=fake` (lihat di bawah).

**Registrasi alumni ≤ 2021** (hidden link, tidak ditautkan dari halaman mana pun): `/register/alumni`.
Jika `ALUMNI_REGISTRATION_KEY` di `.env` diisi, alamatnya menjadi `/register/alumni?key=<kunci>`.
Setelah mendaftar, alumni langsung bisa login dengan NIM lama **atau** email pribadi + password yang baru dibuat.

## Mode SSO: `fake` vs `bridge`

Diatur lewat `SSO_DRIVER` di `.env`:

| Mode              | Untuk                                   | Cara kerja                                                                                                                         |
| ----------------- | --------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `fake` (bawaan) | development di Laragon, test            | Tidak menghubungi server kampus. Akun SSO diambil dari `config/sso.php` → `fake_users`, yang bentuknya meniru respons Bridge. |
| `bridge`        | uji coba dengan SSO kampus / production | Memanggil Bridge API v2: `POST /api/v2/portal/login` → token → `GET /api/v2/profil`. Isi `SSO_BRIDGE_URL`.                 |

Variabel lain: `SSO_TIMEOUT` (detik, bawaan 10), `SSO_EMAIL_FALLBACK_DOMAIN` (email pengganti bila profil SSO tanpa email),
`SSO_PASSWORD_RESET_URL` (tautan "Lupa password?" ke portal SSO).

⚠️ Bentuk respons `/api/v2/profil` **belum diketahui pasti**, jadi pemetaan field-nya dibuat bisa diatur di `config/sso.php`.
Penjelasan & cara menyesuaikannya: [`docs/SSO.md`](docs/SSO.md).

## Menjalankan test

```bash
php artisan test
```

Test memakai SQLite in-memory (lihat `phpunit.xml`), jadi tidak menyentuh database Laragon.
Pastikan ekstensi `pdo_sqlite` aktif. Berkas uji ditulis ke disk palsu (`Storage::fake`), bukan ke `storage/`.

Menjalankan sebagian, mis. hanya fitur tiket: `php artisan test tests/Feature/Tickets`
atau satu file: `php artisan test --filter=ServiceSettingsTest`.

## Struktur penting

| Folder / file                               | Isi                                                                           |
| ------------------------------------------- | ----------------------------------------------------------------------------- |
| `app/Enums`                               | `Role`, `UserType`, `TicketStatus`, `NotificationType`                |
| `app/Models`                              | 12 model sesuai tabel PRD + relasi & scope                                    |
| `app/Services/CategoryCatalogService.php` | Katalog Kategori Layanan Standar (dipakai seeder & saat fakultas baru dibuat) |
| `app/Services/CategoryManager.php`        | Aturan Pengaturan Layanan (kategori standar vs tambahan)                      |
| `app/Services/StaffSpecialtyService.php`  | Spesialisasi staff + ringkasan "Kelompok tanpa Staff"                        |
| `app/Services/Tickets`                    | Buat tiket, nomor tiket, lampiran, pilihan kategori, thread                   |
| `app/Policies`                            | Siapa boleh melihat/mengubah apa (tiket, lampiran, kategori, staff)           |
| `app/Exceptions/BusinessRuleException.php` | Error aturan bisnis berkode PRD (`INVALID_CATEGORY`, `THREAD_LIMIT_REACHED`, ...) |
| `app/Services/Sso`                        | Login SSO: interface, klien Bridge, klien simulasi, sinkronisasi user         |
| `app/Services/Auth/LoginService.php`      | Alur login satu kolom (email → lokal, NIM alumni ≤ 2021 → lokal, NIM/NIDN/NIP lain → SSO) |
| `app/Http/Middleware`                     | `role`, akun aktif, kunci hidden link alumni                                |
| `config/sso.php`                          | Konfigurasi SSO + pemetaan field profil + akun simulasi                       |
| `lang/id`                                 | Pesan validasi & autentikasi Bahasa Indonesia                                 |
| `docs/progres`                            | Catatan belajar per milestone (bahan laporan KP)                              |
