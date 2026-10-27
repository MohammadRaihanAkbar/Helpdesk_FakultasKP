# Catatan Belajar M6: Email Notifikasi, Peran Dekan, Laporan Kerja Staff, Verifikasi Alumni, dan Halaman Depan

Catatan ini lanjutan dari `M5-super-admin-notifikasi-keamanan.md`. Milestone 6 berisi **5 tambahan dari dosen
pembimbing**. Sebagian besar di luar PRD v6.2 (PRD perlu direvisi, lihat bagian 9), kecuali email notifikasi yang
memang ada di PRD (F-NTF, PRD 6.4) tapi di M5 sengaja ditunda.

```
1. Email:   peristiwa tiket ─▶ TicketNotifier ─▶ lonceng (seperti M5) + email (antrean, 3x coba) ─▶ Gmail SMTP
            tiap jam: helpdesk:pengingat-rating ─▶ tiket Selesai belum dirating ─▶ email + lonceng "Yuk beri rating"
2. Dekan:   login ─▶ /dekan/dashboard (statistik + hasil kerja Staff fakultasnya) ─▶ /dekan/laporan (+ unduh) — hanya baca
3. Staff:   /staff/laporan ─▶ 7 / 14 / 30 / 90 hari / rentang tanggal ─▶ angka hasil kerja + daftar tiket + unduh
4. Alumni:  /register/alumni (+ captcha) ─▶ akun MENUNGGU ─▶ Admin Fakultas asal menyetujui/menolak ─▶ baru bisa login
5. Publik:  / = halaman depan (profil helpdesk, foto Gedung Siber, cara kerja, kelompok layanan, Tren Tiket) ─▶ Masuk
```

| Tambahan                                        | Permintaan pembimbing                                 | Halaman / perintah                                   |
| ----------------------------------------------- | ----------------------------------------------------- | ---------------------------------------------------- |
| Email notifikasi alur tiket + email Ping        | "notifikasi lewat email itu harus"                    | semua peristiwa M5, akun `helpdeskfakultas@gmail.com` |
| Pengingat rating lewat email                    | "kalo user belum rating bisa dikirim email"           | `php artisan helpdesk:pengingat-rating` (tiap jam)   |
| Peran Dekan (pantau statistik + ekspor)         | "role dekan untuk pemantauan dashboard … serta export" | `/dekan/dashboard`, `/dekan/laporan`                 |
| Laporan hasil kerja Staff                       | "laporan hasil kerja … filter per berapa harinya"     | `/staff/laporan`                                     |
| Captcha + verifikasi Admin untuk alumni         | "captcha … diverifikasi admin dahulu"                 | `/register/alumni`, `/admin/verifikasi-alumni`       |
| Halaman depan publik + tren tiket               | "landing page … statistik tren tiket … gedung siber"  | `/`                                                  |

Prinsipnya sama seperti sebelumnya: **tidak ada library baru** (composer.json & package.json tidak berubah), tidak
ada layanan pihak ketiga (bukan Mailgun/SendGrid, bukan Google reCAPTCHA), dan semua dibangun di atas kode M1–M5.

---

## 1. Peta file baru

| Lokasi                                                          | Isi                                                                 |
| --------------------------------------------------------------- | ------------------------------------------------------------------- |
| `config/helpdesk.php`                                           | Angka aturan M6 (retry email, jadwal pengingat, captcha, cache halaman depan) |
| `app/Notifications/HelpdeskMail.php`, `TicketMail.php`          | Kelas dasar semua email: antrean, 3x coba, cek "boleh dikirimi email?" |
| `app/Notifications/*Mail.php`                                   | Satu kelas per jenis email (tiket dibuat, status, balasan, selesai, batas thread, Ping, pengingat rating, alumni) |
| `resources/views/vendor/{mail,notifications}/*`                 | Tampilan email: bahasa Indonesia, warna hijau-emas helpdesk          |
| `app/Listeners/SendPingEmails.php`, `LogSentEmail.php`          | Email Ping (event `TicketPinged` dari M4) + catatan email terkirim   |
| `app/Services/Tickets/RatingReminderService.php`, `app/Console/Commands/SendRatingReminders.php` | Pengingat rating |
| `app/Http/Controllers/Dekan/*`, `resources/views/dekan/*`       | Dashboard & laporan Dekan                                            |
| `app/Services/Reports/FacultyReport.php`                        | Isi Laporan & Analitik + ekspor, dipakai Admin **dan** Dekan         |
| `app/Services/Reports/StaffWorkReport.php`, `Staff/ReportController.php`, `staff/laporan.blade.php` | Laporan Kerja Staff |
| `app/Enums/AccountStatus.php`                                   | `pending` (menunggu) / `approved` (disetujui) / `rejected` (ditolak) |
| `app/Services/Auth/Captcha.php`, `app/Rules/ValidCaptcha.php`   | Captcha buatan sendiri                                               |
| `app/Services/Auth/AlumniVerificationService.php`, `Admin/AlumniVerificationController.php` | Daftar, setujui, tolak alumni |
| `app/Services/Reports/LandingStatistics.php`, `LandingController.php`, `landing.blade.php`, `components/layouts/public.blade.php` | Halaman depan |
| `public/images/gedung-siber{.webp,.jpg,-960.webp}`              | Foto Gedung Siber yang sudah dikecilkan (35–173 KB, aslinya 1,7 MB)  |
| 3 migration `2026_10_28_*`                                       | Kolom pengingat rating, peran `dekan`, status akun                   |
| `tests/Feature/{Notifications,Dekan,Alumni}/*`, `Staff/StaffWorkReportTest`, `View/LandingPageTest` | Tes baru M6 |

---

## 2. Email notifikasi (tambahan 1)

### 2.1 Kenapa tanpa pihak ketiga

Laravel sudah punya **mailer SMTP** bawaan (`config/mail.php`, memakai Symfony Mailer yang sudah ikut terpasang).
Gmail menyediakan server SMTP (`smtp.gmail.com`), jadi cukup "login" ke Gmail helpdesk dari aplikasi. Tidak perlu
daftar ke layanan email mana pun dan tidak ada paket composer baru.

Pengaturannya di `.env`:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtp          # smtp + port 587 = STARTTLS (PRD 6.4). Laravel 13 tidak lagi memakai MAIL_ENCRYPTION
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=helpdeskfakultas@gmail.com
MAIL_PASSWORD=            # App Password Gmail, 16 huruf
MAIL_FROM_ADDRESS=helpdeskfakultas@gmail.com
MAIL_FROM_NAME="Helpdesk Fakultas Terpadu"
```

**Kenapa `MAIL_SCHEME=smtp`, bukan `tls`?** Di Laravel 11+ kunci `encryption` diganti `scheme`. Nilainya `smtp`
(lalu Symfony otomatis naik ke STARTTLS kalau server mendukung — Gmail mendukung) atau `smtps` (SSL langsung, port 465).

### 2.2 App Password Gmail (wajib)

Gmail **menolak** login SMTP dengan password biasa. Yang dipakai adalah **App Password**:

1. Login ke akun Google `helpdeskfakultas@gmail.com` → **Kelola Akun Google** → **Keamanan**.
2. Aktifkan **Verifikasi 2 Langkah** (2-Step Verification) — App Password hanya muncul kalau ini aktif.
3. Buka `https://myaccount.google.com/apppasswords`, beri nama mis. "Helpdesk Laragon", klik **Buat**.
4. Salin 16 huruf yang muncul (spasinya boleh dibuang) ke `MAIL_PASSWORD=` di `.env`, lalu `php artisan config:clear`.

App Password = kunci penuh ke akun Gmail itu. Jangan di-commit, jangan dikirim di chat. Kalau bocor: hapus di halaman
yang sama lalu buat yang baru.

**Mencoba tanpa mengirim**: `MAIL_MAILER=log` → isi email ditulis ke `storage/logs/laravel.log`. Cocok untuk demo.

### 2.3 Ke mana email dikirim

Penerima = `users.email`. Untuk akun SSO itu email dari Bridge `/api/v2/profil`:

- `SsoUserSynchronizer` sekarang **memperbarui email setiap login** kalau Bridge memberi email yang valid.
- Kalau profil SSO tidak berisi email, akun diberi email pengganti `{nim}@SSO_EMAIL_FALLBACK_DOMAIN` (sejak M1).
  Email pengganti itu **tidak pernah** menimpa email asli yang sudah tersimpan.
- Akun dengan email kosong atau email pengganti **hanya** mendapat notifikasi lonceng. Aturannya ada di SATU tempat:
  `User::canReceiveEmail()` (dipakai `routeNotificationForMail()` dan `HelpdeskMail::via()`).

### 2.4 Peristiwa yang mengirim email

| Peristiwa (PRD)                       | Penerima            | Isi / tautan                                       |
| ------------------------------------- | ------------------- | -------------------------------------------------- |
| Tiket dibuat (F-NTF-01)               | pelapor             | nomor tiket, fakultas, layanan → detail tiket      |
| Status jadi Diproses / Ditolak (F-NTF-02) | pelapor         | nama Staff / alasan penolakan                      |
| Balasan baru dari Staff (F-NTF-03)    | pelapor             | cuplikan pesan → thread tiket                      |
| Tiket Selesai (F-NTF-04)              | pelapor             | tombol **Beri Rating** → `/history/{id}#rating`    |
| Batas 20 pesan thread (F-NTF-07)      | pelapor + Staff     | → detail tiket                                     |
| Ping dari Admin (F-NTF-06)            | Staff yang di-ping  | nomor tiket, lama menunggu → detail/antrean        |
| Pengingat rating (M6)                 | pelapor             | → form rating                                      |
| Alumni baru mendaftar (M6)            | Admin Fakultas asal | → `/admin/verifikasi-alumni`                       |
| Akun alumni disetujui / ditolak (M6)  | alumni              | → `/login` / alasan penolakan                      |

Kalau penerima belum login, tautan membawa ke `/login` dulu lalu kembali ke halaman tujuan (intended URL bawaan Laravel).

### 2.5 Cara kerjanya di kode

`TicketNotifier` dari M5 tetap "satu pintu". Bedanya, setelah menulis baris notifikasi lonceng, ia juga mengirim
email lewat kelas `*Mail`. Semua kelas email turunan `HelpdeskMail`:

- `ShouldQueue` → email dimasukkan ke **antrean** (tabel `jobs`), jadi halaman tidak menunggu Gmail (bisa 1–3 detik);
- `$tries = 3` + `backoff()` 60 dan 300 detik → PRD 6.4 "3x percobaan dengan jeda";
- `afterCommit()` → email baru dikirim setelah transaksi database berhasil (tidak ada email untuk tiket yang batal tersimpan);
- pengiriman dibungkus `rescue()` → kalau antrean/email bermasalah, tiket & notifikasi lonceng **tetap** tersimpan.

Setiap email yang benar-benar terkirim dicatat (`LogSentEmail`) sebagai baris notifikasi bertipe `email`, sehingga
bisa dicek di database email apa saja yang sudah keluar.

`MAIL_NOTIFICATIONS=false` mematikan semua email (kembali seperti M5, hanya lonceng).

### 2.6 Antrean & penjadwal: harus ada yang "menjalankan"

Email masuk antrean, dan pengingat rating dijadwalkan tiap jam. Keduanya butuh proses yang terus berjalan:

**Di Laragon (dua terminal tambahan, biarkan terbuka):**

```bash
php artisan queue:work        # mengirim email dari antrean
php artisan schedule:work     # menjalankan jadwal (pengingat rating tiap jam)
```

**Di server (Linux):**

- cron: `* * * * * cd /path/helpdesk-fakultas && php artisan schedule:run >> /dev/null 2>&1`
- worker antrean dijaga **Supervisor** (`command=php /path/helpdesk-fakultas/artisan queue:work --tries=3 --sleep=3`,
  `autostart=true`, `autorestart=true`). Setelah update kode: `php artisan queue:restart`.

Alternatif paling sederhana: `QUEUE_CONNECTION=sync` → email langsung dikirim saat itu juga tanpa `queue:work`,
tapi halaman jadi lambat tiap ada email dan percobaan ulang tidak berlaku. Bawaan tetap `database`.

Batas Gmail biasa ±**500 email/hari**. Untuk satu fakultas cukup; kalau nanti dipakai semua fakultas dan sering
mentok, minta akun email kampus (SMTP kampus) — cukup ganti `MAIL_HOST/USERNAME/PASSWORD`, kodenya tidak berubah.

### 2.7 Pengingat rating

`php artisan helpdesk:pengingat-rating` (jadwal tiap jam di `routes/console.php`, `withoutOverlapping`):

| Aturan                                   | Bawaan (`.env`)                          |
| ---------------------------------------- | ---------------------------------------- |
| Pengingat pertama                        | 24 jam setelah selesai (`RATING_REMINDER_FIRST_AFTER_HOURS`) |
| Jarak antar pengingat                    | 48 jam (`RATING_REMINDER_INTERVAL_HOURS`) |
| Paling banyak                            | 2 kali (`RATING_REMINDER_MAX`)           |
| Tiket yang selesai lebih dari            | 14 hari tidak diingatkan (`RATING_REMINDER_MAX_AGE_DAYS`) |
| Berhenti                                 | begitu pelapor memberi rating            |
| Tiket Ditolak                            | tidak pernah (memang tidak bisa dirating) |

Jejaknya di kolom baru `tickets.rating_reminder_count` dan `tickets.last_rating_reminder_at`.

**Idempotent** = dijalankan berkali-kali hasilnya sama. Penghitung dinaikkan dengan `UPDATE … WHERE
rating_reminder_count = <nilai lama>`; kalau dua proses berjalan bersamaan, hanya satu yang "menang" dan mengirim.
Menjalankan perintah dua kali berturut-turut → yang kedua mengirim 0.

Untuk demo: `--lihat` (hanya menampilkan tiket yang akan diingatkan, tidak mengirim) dan `--paksa` (abaikan jeda 24/48 jam,
batas maksimal tetap berlaku).

Kenapa ada batas 14 hari? Supaya saat fitur ini pertama kali dinyalakan, pelapor tiket-tiket lama tidak tiba-tiba
menerima banyak email.

---

## 3. Peran Dekan (tambahan 2)

### 3.1 Menambah nilai enum di migration baru

Kolom `role` adalah `enum` di MySQL. Migration `2026_10_28_110000_add_dekan_to_users_role` memakai `->change()` untuk
menambah `dekan`. Di MySQL itu menjadi `ALTER TABLE … MODIFY role ENUM(…, 'dekan')`; di SQLite (dipakai tes) enum
hanyalah `CHECK`, dan Laravel 13 membangun ulang tabelnya. Satu kode, dua database. Migration lama tidak disentuh
(alasannya sama seperti di catatan M5 bagian 2).

### 3.2 Apa yang boleh Dekan

Dekan terikat **satu fakultas** (`faculty_id`) dan hanya bisa **melihat**:

| Halaman             | Isi                                                                                   |
| ------------------- | ------------------------------------------------------------------------------------- |
| `/dekan/dashboard`  | KPI fakultas (total per status, SLA compliance, rata-rata penyelesaian, rata-rata waktu sampai diambil, CSAT), tren 7/30 hari, per Kelompok Layanan, tabel **hasil kerja Staff** (diambil, selesai, ditolak, diproses, rata-rata penyelesaian, SLA %, CSAT) |
| `/dekan/laporan`    | Laporan & Analitik yang **sama persis** dengan Admin + **Unduh CSV / Excel** (kolom US-15) |

Tidak ada tombol ambil/tolak/Ping, tidak ada menu Staff/Layanan, tidak bisa membuka detail tiket. Penjaganya
middleware `role:dekan` per grup route, jadi Dekan yang mengetik `/admin/...` atau `/staff/...` mendapat 403.

Supaya tidak ada dua salinan kode laporan, isi `Admin\ReportController` dipindah ke `FacultyReport` (commit
`refactor(laporan)`), lalu dipakai kedua controller. Satu perbedaan: di ekspor & tabel Dekan, nama pelapor tiket
**Integritas Dosen** ditampilkan "Dirahasiakan" (`TicketExport::maskSensitiveReporters()`) — Dekan memantau angka,
bukan identitas pelapor kategori sensitif.

### 3.3 Siapa yang membuat Dekan

Super Admin di `/super-admin/users`:

- **Tunjuk** pengguna yang sudah ada menjadi Dekan + pilih fakultas (sama seperti menunjuk Admin), **cabut** → kembali `end_user`;
- **Buat akun Dekan** (nama, email, password, fakultas, NIP opsional) untuk dekan yang belum pernah login SSO.

Login SSO ulang **tidak** mengubah peran pengguna yang sudah dipromosikan (`SsoUserSynchronizer` hanya mengisi
`role` saat akun baru dibuat). Ada tes untuk itu.

---

## 4. Laporan Kerja Staff (tambahan 3)

`/staff/laporan` (menu "Laporan Kerja"). Filter: **7 / 14 / 30 / 90 hari terakhir** atau **rentang tanggal sendiri**
(maks. 1 tahun).

Tiket yang masuk laporan = tiket yang ditangani Staff itu **dan** diambil ATAU ditutup dalam periode. Jadi tiket yang
diambil minggu lalu dan baru selesai hari ini ikut di laporan 7 hari. Angkanya (rumus sama dengan `TicketMetrics`):
diambil, selesai, ditolak, masih diproses, rata-rata waktu penyelesaian, rata-rata respons pertama, SLA compliance %,
rata-rata CSAT. Di bawahnya tabel tiket (paginasi) dan tombol **Unduh CSV / Excel** (format & kolom sama dengan
ekspor Admin).

Keamanannya sederhana: `StaffWorkReport::query()` **selalu** mengunci `handler_id = Staff yang login`, jadi tidak ada
parameter URL yang bisa dipakai untuk melihat tiket Staff lain.

---

## 5. Captcha & verifikasi alumni (tambahan 4)

### 5.1 Captcha buatan sendiri

Google reCAPTCHA butuh kunci & internet (tidak jalan di Laragon offline) dan berarti pihak ketiga. Jadi dibuat sendiri
(`App\Services\Auth\Captcha`):

- soal penjumlahan, mis. `12 + 7 = ?`, **digambar sebagai SVG** dari garis-garis (bukan elemen `<text>`), tiap angka
  diputar/digeser acak + garis & titik pengganggu. Tidak butuh ekstensi GD;
- yang disimpan di session hanya **hash** jawaban (HMAC dengan `APP_KEY`);
- **sekali pakai** (dihapus begitu dicek, benar atau salah) dan **kedaluwarsa 10 menit**;
- tombol "Ganti soal" (Alpine) memuat gambar baru dari `/register/alumni/captcha`;
- route POST registrasi tetap di-throttle 6x/menit per IP, gambar captcha 30x/menit.

Di tes, captcha dimatikan lewat `CAPTCHA_ENABLED=false` di `phpunit.xml`; `CaptchaTest` menyalakannya lagi dan
menguji jawaban salah/benar/kedaluwarsa/dipakai ulang.

Batasannya: captcha sederhana menahan bot iseng, bukan program khusus pembaca gambar. Pengaman utamanya tetap
verifikasi Admin di bawah.

### 5.2 Status akun

Migration `2026_10_28_120000_add_account_status_to_users_table` menambah `account_status` (`pending` = menunggu,
`approved` = disetujui, `rejected` = ditolak), `verified_at`, `verified_by`, `rejection_reason`. **Default `approved`**, jadi semua akun lama (termasuk
alumni yang sudah mendaftar sebelum M6) tetap bisa login. Hanya registrasi alumni baru yang mulai `pending`.

`LoginService` mengecek status **setelah** password benar (supaya orang lain tidak bisa mengintip status akun
dengan password asal):

- menunggu → "Akun Anda masih menunggu verifikasi Admin Fakultas …"
- ditolak → pesan + alasan penolakan.

`EnsureUserIsActive` juga mengeluarkan akun yang statusnya berubah saat sedang login.

### 5.3 Alur verifikasi

1. Alumni mengisi `/register/alumni` + captcha → halaman **"Pendaftaran diterima"** (`/register/alumni/terkirim`).
2. Admin Fakultas **asal** (`origin_faculty_id`) mendapat lonceng + email. Menu **Verifikasi Alumni** menampilkan jumlah
   yang menunggu, dan dashboard Admin menampilkan kotak peringatan (menu ini tidak muat di bottom nav ponsel).
3. Admin membuka `/admin/verifikasi-alumni` (nama, NIM lama, tahun lulus, email, fakultas asal, waktu daftar) →
   **Setujui** atau **Tolak** (alasan wajib). Alumni mendapat email hasilnya.
4. Alumni yang ditolak boleh mendaftar lagi dengan NIM/email yang sama: baris lama dipakai ulang, status kembali `pending`.

Admin fakultas lain mendapat 403 (`UserPolicy::verifyAlumni`). Kalau fakultas asal belum punya Admin aktif, tidak ada
yang mendapat notifikasi (dicatat di log) — Super Admin perlu menunjuk Admin dulu.

---

## 6. Halaman depan publik (tambahan 5)

`/` sekarang halaman depan, bukan langsung ke `/login`. `/login` tetap seperti biasa. Pengunjung yang sudah login
melihat tombol **Buka Dashboard** (ke dashboard sesuai peran) alih-alih **Masuk**.

Isinya: hero dengan foto **Gedung Siber** (lapisan gelap + kartu kaca/glassmorphism, token warna PRD 6: `#0B0F19`,
`#6366F1`, `#06B6D4`, `#10B981`, dipadukan dengan hijau-emas yang sudah dipakai di aplikasi), profil helpdesk (untuk
siapa, 6 fakultas, kenapa: satu pintu, SLA jelas, transparan), **Cara kerja** 4 langkah, **Kelompok layanan** dari
katalog standar, dan **Tren tiket**:

- angka agregat: total tiket, % selesai, rata-rata penyelesaian, SLA compliance;
- grafik volume tiket 30 hari (komponen `charts/bars` sendiri, tanpa library grafik);
- **Sedang ramai**: 5 Kelompok Layanan terbanyak 30 hari terakhir + perubahan dibanding 30 hari sebelumnya (▲/▼/baru);
- Jenis masalah terbanyak dan **Performa SLA Fakultas** (komponen M5).

Aturan privasi (`LandingStatistics`): hanya angka, tanpa nomor tiket/judul/nama. Kelompok **Integritas Dosen** tidak
pernah muncul di rincian tren, hanya ikut di angka total — sama seperti peringkat SLA di M5. Statistik disimpan di
cache 15 menit (`LANDING_CACHE_SECONDS`). Link registrasi alumni **tidak** ditautkan (tetap hidden link).

Foto aslinya 1,7 MB. Yang dipakai versi WebP 1600 px (±90 KB), WebP 960 px untuk ponsel (±35 KB) lewat `<picture>`,
dan JPG (±173 KB) untuk browser lama. Semua dari `public/images`, jadi lolos CSP (`img-src 'self'`). Sudah dicek di
Chromium (desktop 1440 px & ponsel 390 px): tidak ada error CSP di Console.

---

## 7. Data demo baru

| Data                                        | Untuk mencoba                                       |
| ------------------------------------------- | --------------------------------------------------- |
| `dekan.fitk@uinssc.test` / `password`       | dashboard & laporan Dekan FITK                      |
| tiket Selesai belum dirating di data demo   | `php artisan helpdesk:pengingat-rating --lihat` / `--paksa` |
| (tidak ada alumni menunggu bawaan)          | daftar sendiri di `/register/alumni`, setujui dengan `admin.fitk@uinssc.test` |

Email akun demo (`@uinssc.test`) bukan alamat sungguhan. Untuk mencoba email asli, ubah email salah satu akun ke
email pribadi lewat HeidiSQL, atau pakai `MAIL_MAILER=log`.

---

## 8. Tes

| File                                              | Yang dites                                                     |
| ------------------------------------------------- | -------------------------------------------------------------- |
| `tests/Feature/Notifications/EmailNotificationTest` | email per peristiwa, penerima, email pengganti tidak dikirimi, antrean + 3x coba, render bahasa Indonesia, `MAIL_NOTIFICATIONS=false` |
| `tests/Feature/Notifications/RatingReminderTest`  | 24 jam pertama, jarak 48 jam, maks. 2, berhenti setelah dirating, Ditolak tidak, idempotent, `--paksa` |
| `tests/Feature/Sso/SsoUserSynchronizerTest` (+2)  | email SSO diperbarui, email pengganti tidak menimpa email asli |
| `tests/Feature/Dekan/*`                           | akses per peran, batas fakultas, KPI & hasil kerja Staff, ekspor = Admin, pelapor sensitif disamarkan, tunjuk/cabut/buat akun, SSO tidak mengubah peran |
| `tests/Feature/Staff/StaffWorkReportTest`         | angka per periode, preset & rentang tanggal, tiket lintas periode, ekspor hanya tiket sendiri |
| `tests/Feature/Alumni/CaptchaTest`                | captcha salah/benar/kedaluwarsa/sekali pakai, gambar SVG       |
| `tests/Feature/Alumni/AlumniVerificationTest`     | akun menunggu tidak bisa login, setujui → bisa, tolak + alasan, admin fakultas lain 403, daftar ulang, akun lama disetujui migration |
| `tests/Feature/View/LandingPageTest`              | `/` untuk tamu, statistik, tanpa kelompok sensitif, tombol dashboard saat login, tanpa link alumni |

Hasil: sebelum M6 **416 tes lulus**, sesudah M6 **492 tes lulus** (4228 assertion) (`php artisan test`).

Trik: `Notification::fake()` lalu `Notification::assertSentTo($user, TicketResolvedMail::class)` untuk memastikan email
"dikirim" tanpa benar-benar mengirim. `$this->travel(25)->hours()` untuk menguji jadwal pengingat.

---

## 9. Yang sengaja ditunda / perlu keputusan

| Hal                                                        | Kapan / siapa | Catatan                                                     |
| ---------------------------------------------------------- | ------------- | ----------------------------------------------------------- |
| App Password Gmail di `.env`                               | Raihan        | tanpa ini email gagal (tercatat di log, lonceng tetap jalan) |
| Contoh respons asli Bridge `/api/v2/profil` (field email)  | pengelola SSO | pastikan field email benar di `config/sso.php`              |
| Tiket milik Staff yang dinonaktifkan                       | pembimbing    | masih sama seperti M5                                       |
| Revisi PRD v6.3 (Dekan, Laporan Staff, verifikasi alumni + captcha, halaman depan, pengingat rating) | Raihan + pembimbing | 5 tambahan ini belum tertulis di PRD v6.2 |
