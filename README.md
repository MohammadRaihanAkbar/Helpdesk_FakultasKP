
# Helpdesk Fakultas Terpadu — UIN SSC

Aplikasi helpdesk (layanan aduan & permohonan informasi) untuk **seluruh fakultas UIN SSC**
(FITK, FUA, FDKI, FEBI, FASYA, FASTIH) dalam satu aplikasi.

- **Pelapor** (mahasiswa, dosen, alumni) membuat tiket ke fakultas tujuan, memantau statusnya, berdiskusi dengan staff, dan memberi rating.
- **Staff Fakultas** mengambil tiket dari antrean sesuai spesialisasi layanannya dan menyelesaikannya (dengan bukti).
- **Admin Fakultas** mengelola staff, spesialisasi, katalog layanan & target SLA, memantau antrean, dan melihat laporan.
- **Super Admin** mengelola data master fakultas dan menunjuk Admin Fakultas.

Login mahasiswa/dosen/alumni ≥ 2022 memakai **SSO kampus** (NIM/NIDN). Staff, admin, dan alumni lulusan ≤ 2021 memakai email + password.

Dibangun dengan **Laravel 13 + Blade + MySQL**, Alpine.js, Tailwind CSS v4 (Vite). Dokumen acuan: PRD v6.2 dan Rencana Teknis.

> Status: **Milestone 1 (fondasi)** — model & relasi, login (lokal + SSO), registrasi alumni, middleware peran,
> tampilan tema UIN (mockup A Terang, responsif) dengan dashboard kosong per peran.

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

   Hasilnya: 6 fakultas, 72 kategori layanan (12 per fakultas), akun demo, dan 4 tiket contoh.
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
| Staff FITK              | `staff.akademik.fitk@uinssc.test` | Akademik, Jadwal Mengajar, Integritas Dosen                                                                |
| Staff FITK              | `staff.umum.fitk@uinssc.test`     | Administrasi, UKT, Fasilitas, IT                                                                           |
| Staff FITK              | `staff.alumni.fitk@uinssc.test`   | Transkrip, PDDIKTI                                                                                         |
| Mahasiswa (SSO)         | NIM `2210110001`                  |                                                                                                            |
| Dosen (SSO)             | NIDN `0412038801`                 |                                                                                                            |
| Alumni lulus 2023 (SSO) | NIM `1910110045`                  | jenis pelapor otomatis *alumni*                                                                          |
| Alumni lulus 2019       | `dedi.alumni2019@example.com`     | tidak ada di SSO, daftar lewat hidden link                                                                 |
| Mahasiswa baru (SSO)    | NIM `2310210007`                  | belum ada di database: akun dibuat otomatis saat login pertama                                             |

Akun SSO di atas hanya bisa dipakai pada mode `SSO_DRIVER=fake` (lihat di bawah).

**Registrasi alumni ≤ 2021** (hidden link, tidak ditautkan dari halaman mana pun): `/register/alumni`.
Jika `ALUMNI_REGISTRATION_KEY` di `.env` diisi, alamatnya menjadi `/register/alumni?key=<kunci>`.

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
Pastikan ekstensi `pdo_sqlite` aktif.

## Struktur penting

| Folder / file                               | Isi                                                                           |
| ------------------------------------------- | ----------------------------------------------------------------------------- |
| `app/Enums`                               | `Role`, `UserType`, `TicketStatus`, `NotificationType`                |
| `app/Models`                              | 12 model sesuai tabel PRD + relasi & scope                                    |
| `app/Services/CategoryCatalogService.php` | Katalog Kategori Layanan Standar (dipakai seeder & saat fakultas baru dibuat) |
| `app/Services/Sso`                        | Login SSO: interface, klien Bridge, klien simulasi, sinkronisasi user         |
| `app/Services/Auth/LoginService.php`      | Alur login satu kolom (email → lokal, NIM/NIDN → SSO)                       |
| `app/Http/Middleware`                     | `role`, akun aktif, kunci hidden link alumni                                |
| `config/sso.php`                          | Konfigurasi SSO + pemetaan field profil + akun simulasi                       |
| `lang/id`                                 | Pesan validasi & autentikasi Bahasa Indonesia                                 |
| `docs/progres`                            | Catatan belajar per milestone (bahan laporan KP)                              |
