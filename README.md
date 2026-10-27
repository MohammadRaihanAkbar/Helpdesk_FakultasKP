
# Helpdesk Fakultas Terpadu — UIN SSC

Aplikasi helpdesk (layanan aduan & permohonan informasi) untuk **seluruh fakultas UIN SSC**
(FITK, FUA, FDKI, FEBI, FASYA, FASTIH) dalam satu aplikasi.

- **Pelapor** (mahasiswa, dosen, alumni) membuat tiket ke fakultas tujuan, memantau statusnya, berdiskusi dengan staff, dan memberi rating.
- **Staff Fakultas** mengambil tiket dari antrean sesuai spesialisasi layanannya dan menyelesaikannya (dengan bukti).
- **Admin Fakultas** mengelola staff, spesialisasi, katalog layanan & target SLA, memantau antrean, dan melihat laporan.
- **Super Admin** mengelola data master fakultas dan menunjuk Admin Fakultas serta Dekan.
- **Dekan** memantau statistik & hasil kerja Staff fakultasnya dan mengunduh laporan (hanya baca).

Login mahasiswa/dosen/alumni ≥ 2022 memakai **SSO kampus** (NIM, atau NIP untuk dosen). Staff dan admin memakai email + password.
Alumni lulusan ≤ 2021 (daftar lewat hidden link) memakai **NIM lama atau email** + password yang dibuat saat registrasi.

Dibangun dengan **Laravel 13 + Blade + MySQL**, Alpine.js, Tailwind CSS v4 (Vite). Dokumen acuan: PRD v6.2 dan Rencana Teknis.

> Status: **Milestone 6 (email notifikasi, peran Dekan, laporan kerja Staff, verifikasi alumni, halaman depan)** — di atas
> M1 (login, peran), M2 (layanan, staff, buat tiket, thread), M3 (alur Staff + rating), M4 (monitoring, Ping, laporan Admin),
> dan M5 (Super Admin, notifikasi in-app, peringkat SLA fakultas, keamanan).
> M6 = 5 tambahan dari pembimbing: **email notifikasi** lewat SMTP Gmail `helpdeskfakultas@gmail.com` (+ **pengingat rating**
> otomatis), peran **Dekan** (dashboard statistik + ekspor, hanya baca), **Laporan Kerja Staff** (7/14/30/90 hari / rentang tanggal),
> **captcha + verifikasi Admin** untuk registrasi alumni, dan **halaman depan publik** dengan tren tiket.
> Catatan lengkap: `docs/progres/M6-email-dekan-laporan-alumni-landing.md`.

## Fitur yang sudah bisa dipakai

| Peran          | Halaman                                   | Isi                                                                                       |
| -------------- | ----------------------------------------- | ----------------------------------------------------------------------------------------- |
| Publik         | `/`                                       | Halaman depan: profil helpdesk, foto Gedung Siber, cara kerja, kelompok layanan, **Tren Tiket** (angka agregat, volume 30 hari, kelompok yang sedang ramai, peringkat SLA fakultas); tombol Masuk / Buka Dashboard |
| Pelapor        | `/dashboard`                              | 4 kartu (Total, Menunggu, Diproses, Selesai), tombol Buat Tiket, 5 tiket terbaru (badge **Balasan baru**), kotak **Notifikasi baru**, **Performa SLA Fakultas** (hijau → merah) |
| Semua peran    | `/notifications`                          | Lonceng: pelapor — tiket dibuat, status berubah, balasan Staff, tiket selesai (klik = form rating), batas 20 pesan; Staff — Ping & batas thread |
| Pelapor        | `/tickets/new`                            | Fakultas → Kelompok Layanan → Jenis Masalah (sesuai jenis pelapor), judul, deskripsi, lampiran JPG/PNG/PDF (≤ 5 MB, ≤ 3) |
| Pelapor        | `/tickets`, `/tickets/{id}`               | Tiket Saya (Menunggu/Diproses), detail, linimasa, thread diskusi maks. 20 pesan            |
| Pelapor        | `/history`, `/history/{id}`               | Riwayat (Selesai/Ditolak), alasan penolakan, bukti penyelesaian, **rating CSAT** 1–5 bintang (sekali), **Buat Ulang Tiket** bila salah fakultas/kategori |
| Admin Fakultas | `/admin/dashboard`                        | Kartu US-10 (Masuk, Menunggu, Diproses, Selesai Hari Ini, Ditolak Bulan Ini), 5 KPI bulan ini (PRD 1.4), **Tiket Menganggur** + Ping, tren 7 hari, tiket per Kelompok Layanan, performa Staff, "Kelompok tanpa Staff" |
| Admin Fakultas | `/admin/queue`, `/admin/tickets/{id}`     | **Monitor Antrean**: tiket aktif fakultas (badge SLA merah/kuning, waktu menunggu), filter status/SLA/kategori/jenis pelapor/staff/tanggal, cari; detail **hanya baca** (thread + catatan internal + bukti + rating + riwayat Ping) |
| Admin Fakultas | `POST /admin/tickets/{id}/ping`           | **Ping / Ingatkan**: Menunggu → semua Staff aktif berspesialisasi kelompok tiket, Diproses → Staff penangan. 1 baris `ping_logs` + 1 notifikasi in-app per staff |
| Admin Fakultas | `/admin/reports`, `/admin/reports/export` | **Laporan & Analitik**: periode 7/30 hari/custom, filter kategori/staff/status, KPI, volume per hari/minggu/bulan, proporsi kelompok (+ rincian jenis), jenis pelapor, performa Staff, tabel tiket; **Unduh CSV / Excel** dengan kolom PRD US-15 |
| Admin Fakultas | `/admin/services`                         | Pohon kategori, ubah SLA/deskripsi/On-Off, kategori tambahan (tambah, ubah, hapus)         |
| Admin Fakultas | `/admin/staff`, `/admin/staff/{id}/specialties` | Buat akun staff, aktif/nonaktif, spesialisasi per Kelompok Layanan, ringkasan "Kelompok tanpa Staff" |
| Staff          | `/staff/dashboard`                        | 6 kartu (Antrean, Sedang Saya Proses, Selesai Hari Ini, Ditolak & Ditangani Bulan Ini, Rata-rata Rating), tiket Mendekati/Melewati SLA, tiket yang sedang diproses |
| Staff          | `/staff/queue`                            | Antrean tiket Menunggu sesuai spesialisasi (tertua di atas, badge SLA merah/kuning), filter kelompok/jenis pelapor/tanggal, cari nomor, tombol **Ambil Tiket** |
| Staff          | `/staff/tickets`, `/staff/tickets/{id}`   | Tiket Saya (Diproses), detail + thread + **Catatan Internal**, **Tandai Selesai** (wajib bukti) / **Tolak** (wajib alasan) dengan dialog konfirmasi |
| Staff          | `/staff/history`, `/staff/history/{id}`   | Riwayat tiket yang pernah ditangani: filter **rentang tanggal ditutup**, status, **kategori (kelompok/jenis)**; bukti, Sesuai/Melewati SLA, rating pelapor |
| Staff          | `/notifications`                          | Teguran **Ping** dari Admin (lonceng di top bar + kotak "Teguran dari Admin" di dashboard); klik = tandai dibaca + buka tiket |
| Staff          | `/staff/laporan`                          | **Laporan Kerja**: periode 7/14/30/90 hari atau rentang tanggal, angka hasil kerja (diambil, selesai, ditolak, diproses, waktu penyelesaian, respons pertama, SLA, CSAT), daftar tiket, **Unduh CSV / Excel** |
| Admin Fakultas | `/admin/verifikasi-alumni`                | **Verifikasi Alumni**: alumni fakultas asal yang baru mendaftar (badge jumlah di menu), Setujui / Tolak (alasan wajib) |
| Dekan          | `/dekan/dashboard`                        | KPI fakultas, tren 7/30 hari, per Kelompok Layanan, tabel **hasil kerja Staff** — hanya baca |
| Dekan          | `/dekan/laporan`                          | Laporan & Analitik sama dengan Admin + **Unduh CSV / Excel** (pelapor kategori sensitif disamarkan) |
| Super Admin    | `/super-admin/dashboard`                  | Fakultas aktif, total tiket, CSAT, SLA Compliance se-universitas, ringkasan per fakultas, **celah coverage Staff** |
| Super Admin    | `/super-admin/faculties`                  | Daftar fakultas + statistik (Admin, Staff, Total Tiket, Kelompok tanpa Staff), tambah/ubah (kode unik; **katalog standar otomatis tersalin**), aktif/nonaktif |
| Super Admin    | `/super-admin/users`                      | Daftar seluruh pengguna (hanya baca), cari NIM/NIP/email/nama, **tunjuk / pindah / cabut Admin Fakultas atau Dekan** (hanya `role` + `faculty_id` yang berubah), **Buat akun Dekan** |

Lampiran disimpan di `storage/app/private` (tidak bisa dibuka lewat URL publik) dan hanya bisa diunduh
lewat aplikasi oleh orang yang berhak. Tidak perlu `php artisan storage:link`.

---

## Kebutuhan

- Windows + [Laragon](https://laragon.org/) (Full) dengan **PHP ≥ 8.3** dan **MySQL/MariaDB**
- [Composer](https://getcomposer.org/) (sudah ada di Laragon)
- [Node.js](https://nodejs.org/) ≥ 20 (untuk Vite)
- Git

Ekstensi PHP yang dipakai: `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip` (wajib untuk ekspor Excel), `bcmath`, `curl`, `xml`
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
   akun demo, 13 tiket contoh di berbagai status (lengkap dengan lampiran, thread, catatan internal, bukti, rating, dan
   Ping), ditambah ±95 tiket **riwayat 5 bulan terakhir** di FITK & FEBI untuk grafik dan laporan Admin (total ±107 tiket).
   Perintah ini **menghapus semua tabel** lalu membuat ulang, jadi hanya untuk development.
6. **Jalankan aplikasi** (empat terminal):

   ```bash
   npm run dev              # terminal 1: Vite (CSS/JS, auto-refresh)
   php artisan serve        # terminal 2: server Laravel -> http://localhost:8000
   php artisan queue:work   # terminal 3: mengirim email dari antrean (M6)
   php artisan schedule:work  # terminal 4: jadwal, mis. pengingat rating tiap jam (M6)
   ```

   Atau pakai virtual host Laragon: klik kanan Laragon → *Reload* (atau *Apache/Nginx → Reload*), lalu buka
   `http://helpdesk-fakultas.test`. Ubah `APP_URL` di `.env` menjadi alamat tersebut. `npm run dev` tetap perlu dijalankan
   (atau `npm run build` sekali jika tidak sedang mengubah tampilan).

## Akun demo

Semua password: **`password`**. Login di `/login` (satu kolom untuk NIM / NIP / Email).

| Peran                   | Login                               | Keterangan                                                                                                 |
| ----------------------- | ----------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Super Admin             | `superadmin@uinssc.test`          | menu Dashboard, Fakultas, Pengguna (M5)                                                                    |
| Dekan FITK              | `dekan.fitk@uinssc.test`          | dashboard statistik + laporan & ekspor FITK, hanya baca (M6)                                              |
| Admin Fakultas          | `admin.fitk@uinssc.test`          | pola sama: `admin.fua@…`, `admin.fdki@…`, `admin.febi@…`, `admin.fasya@…`, `admin.fastih@…` |
| Staff FITK              | `staff.akademik.fitk@uinssc.test` | Sistem Akademik, Jadwal Mengajar — antrean berisi tiket merah/kuning/aman + 1 tiket Diproses belum dibalas |
| Staff FITK              | `staff.umum.fitk@uinssc.test`     | Administrasi, UKT, Fasilitas, IT, Laboratorium Microteaching (kategori tambahan)                           |
| Staff FITK              | `staff.alumni.fitk@uinssc.test`   | Transkrip, PDDIKTI                                                                                         |
| Staff FITK              | `staff.dekanat.fitk@uinssc.test`  | Integritas Dosen (kategori sensitif) — nama pelapor disamarkan sampai tiket diambil                       |
| Staff FITK (nonaktif)   | `staff.cuti.fitk@uinssc.test`     | akun dinonaktifkan: login ditolak (contoh fitur aktif/nonaktif)                                            |
| Staff FEBI              | `staff.umum.febi@uinssc.test`     | untuk mencoba bahwa Admin FITK tidak bisa menyentuh staff FEBI                                             |
| Pelapor data laporan    | `siti.aminah@uinssc.test`, dst.   | 7 pelapor tambahan pemilik tiket riwayat (M4). Tidak ada di simulasi SSO, jadi tidak dipakai untuk login. |
| Mahasiswa (SSO)         | NIM `2210110001`                  | punya 8 tiket demo: menunggu, diproses (ada thread), selesai (ada yang belum dirating), ditolak "salah fakultas/kategori", sensitif |
| Dosen (SSO)             | NIP `198804122015031001`          | bisa juga dengan NIDN `0412038801` (akun yang sama)                                                        |
| Dosen FEBI (SSO)        | NIP `197905152006042001`          | profil SSO tanpa NIDN: hanya kolom `nip` yang terisi; akun dibuat otomatis saat login pertama              |
| Alumni lulus 2023 (SSO) | NIM `1910110045`                  | jenis pelapor otomatis *alumni*                                                                          |
| Alumni lulus 2019       | NIM `1510110023` atau `dedi.alumni2019@example.com` | tidak ada di SSO, daftar lewat hidden link; dicek di database lokal                  |
| Mahasiswa baru (SSO)    | NIM `2310210007`                  | belum ada di database: akun dibuat otomatis saat login pertama                                             |

Akun SSO di atas hanya bisa dipakai pada mode `SSO_DRIVER=fake` (lihat di bawah).

**Registrasi alumni ≤ 2021** (hidden link, tidak ditautkan dari halaman mana pun): `/register/alumni`.
Jika `ALUMNI_REGISTRATION_KEY` di `.env` diisi, alamatnya menjadi `/register/alumni?key=<kunci>`.
Form registrasi memakai **captcha** (soal penjumlahan). Akun baru berstatus **menunggu** dan baru bisa login (NIM lama **atau**
email pribadi + password) setelah **disetujui Admin Fakultas asal** di `/admin/verifikasi-alumni` (M6).

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

## Email notifikasi (M6)

Email dikirim dengan mailer SMTP bawaan Laravel lewat Gmail `helpdeskfakultas@gmail.com` — tanpa layanan pihak ketiga.
Penerima = email pengguna (untuk akun SSO: email dari Bridge `/profil`). Akun yang hanya punya email pengganti
`…@SSO_EMAIL_FALLBACK_DOMAIN` cukup mendapat notifikasi lonceng.

1. Di akun Google `helpdeskfakultas@gmail.com`: **Keamanan → Verifikasi 2 Langkah** → aktifkan.
2. Buka `https://myaccount.google.com/apppasswords` → buat App Password (16 huruf).
3. Isi `.env`: `MAIL_MAILER=smtp`, `MAIL_SCHEME=smtp`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`,
   `MAIL_USERNAME=helpdeskfakultas@gmail.com`, `MAIL_PASSWORD=<App Password>`, `MAIL_FROM_ADDRESS=helpdeskfakultas@gmail.com`,
   lalu `php artisan config:clear`. **Jangan pernah commit App Password.**
4. Jalankan `php artisan queue:work` (pengirim email) dan `php artisan schedule:work` (pengingat rating tiap jam).

Mencoba tanpa mengirim: `MAIL_MAILER=log` (email ditulis ke `storage/logs/laravel.log`). Tanpa worker antrean: `QUEUE_CONNECTION=sync`
(lebih lambat). Pengingat rating bisa dijalankan manual: `php artisan helpdesk:pengingat-rating --lihat` (pratinjau) atau `--paksa`.
Di server: cron `* * * * * php artisan schedule:run` + `queue:work` dijaga Supervisor. Batas Gmail ±500 email/hari.

## Keamanan (M5)

- Semua respons membawa header `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`.
  CSP sudah mengizinkan Alpine.js (`'unsafe-eval'`), style inline, dan server Vite saat `npm run dev` (dibaca dari `public/hot`),
  jadi tidak perlu dimatikan di Laragon. Kalau ada masalah tampilan: isi `CSP_REPORT_ONLY=true` di `.env` (hanya
  melapor di Console browser), lalu cek pesan di Console. Pengaturan lengkap: `config/security.php`.
- Batas umum **60 request/menit per akun** yang login (PRD 6.3), selain batas khusus login, tiket, dan Ping.

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
| `app/Enums`                               | `Role`, `UserType`, `TicketStatus`, `NotificationType`, `AccountStatus` |
| `app/Models`                              | 12 model sesuai tabel PRD + relasi & scope                                    |
| `app/Services/CategoryCatalogService.php` | Katalog Kategori Layanan Standar (dipakai seeder & saat fakultas baru dibuat) |
| `app/Services/CategoryManager.php`        | Aturan Pengaturan Layanan (kategori standar vs tambahan)                      |
| `app/Services/StaffSpecialtyService.php`  | Spesialisasi staff + ringkasan "Kelompok tanpa Staff"                        |
| `app/Services/Tickets`                    | Buat tiket, nomor tiket, lampiran, pilihan kategori, thread, alur staff (`TicketWorkflowService`: ambil/selesai/tolak), rating |
| `app/Services/Reports`                    | KPI PRD 1.4 (`TicketMetrics`), filter laporan (`ReportFilters`), ekspor CSV/XLSX (`TicketExport`, library openspout) |
| `app/Services/Tickets/TicketNotifier.php` | Semua notifikasi alur tiket: lonceng + email (satu pintu) |
| `app/Notifications`, `config/helpdesk.php` | Email (antrean, 3x coba) + pengaturan M6 (pengingat rating, captcha, halaman depan) |
| `app/Services/Reports/{FacultyReport,StaffWorkReport,LandingStatistics}.php` | Laporan Admin/Dekan, Laporan Kerja Staff, statistik halaman depan |
| `app/Services/Auth/{Captcha,AlumniVerificationService}.php` | Captcha buatan sendiri + verifikasi akun alumni |
| `app/Services/FacultyService.php`, `AdminAppointmentService.php` | Super Admin: fakultas + statistik/celah coverage, tunjuk/cabut Admin |
| `app/Http/Middleware/SecurityHeaders.php`, `config/security.php` | Header keamanan (CSP, X-Frame-Options, …), bisa diatur lewat `.env` |
| `app/Services/Tickets/PingService.php`    | Ping / Ingatkan: penerima, `ping_logs`, notifikasi in-app, event `TicketPinged` (dipakai email Ping M6) |
| `app/Policies`                            | Siapa boleh melihat/mengubah apa (tiket, lampiran, kategori, staff)           |
| `app/Exceptions/BusinessRuleException.php` | Error aturan bisnis berkode PRD (`INVALID_CATEGORY`, `THREAD_LIMIT_REACHED`, ...) |
| `app/Services/Sso`                        | Login SSO: interface, klien Bridge, klien simulasi, sinkronisasi user         |
| `app/Services/Auth/LoginService.php`      | Alur login satu kolom (email → lokal, NIM alumni ≤ 2021 → lokal, NIM/NIP lain → SSO) |
| `app/Http/Middleware`                     | `role`, akun aktif, kunci hidden link alumni, header keamanan               |
| `config/sso.php`                          | Konfigurasi SSO + pemetaan field profil + akun simulasi                       |
| `lang/id`                                 | Pesan validasi & autentikasi Bahasa Indonesia                                 |
| `docs/progres`                            | Catatan belajar per milestone (bahan laporan KP)                              |
