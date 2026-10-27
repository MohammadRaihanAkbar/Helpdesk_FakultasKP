# Catatan Belajar M5: Super Admin, Notifikasi In-App, Peringkat SLA, dan Keamanan

Catatan ini lanjutan dari `M4-admin-monitoring-laporan.md`. Milestone 5 menutup sisa kebutuhan PRD v6.2 yang
belum ada sampai M4: peran **Super Admin**, **notifikasi** alur tiket, **Peringkat Performa SLA Fakultas** di
dashboard pelapor, dan **header keamanan**. Sebelum itu ada satu perapian: dosen sekarang diidentifikasi dengan **NIP**.

```
Super Admin: Dashboard global ─▶ Fakultas (tambah/ubah/nonaktif, katalog otomatis) ─▶ Pengguna (tunjuk/cabut Admin)
Pelapor:     buat tiket / status berubah / Staff membalas ─▶ lonceng + kotak "Notifikasi baru" + badge "Balasan baru"
             dashboard ─▶ Performa SLA Fakultas (hijau → merah)
Semua:       header CSP & kawan-kawan + batas 60 request/menit per akun
```

| Fitur                                             | PRD                                   | Halaman / endpoint                                  |
| ------------------------------------------------- | ------------------------------------- | --------------------------------------------------- |
| Dosen pakai NIP, label "NIM / NIP / Email"         | US-01 (keputusan NIP)                 | `/login`, kolom baru `users.nip`                    |
| Master Fakultas + statistik + celah coverage      | US-17, F-SPA-01                       | `/super-admin/faculties`                            |
| Daftar pengguna + tunjuk/cabut Admin Fakultas     | US-18, F-SPA-02, F-SPA-04             | `/super-admin/users`, `PATCH /super-admin/users/{id}/role` |
| Dashboard metrik global                           | F-DSB-04, F-SPA-03                    | `/super-admin/dashboard`                            |
| Notifikasi in-app alur tiket                      | F-NTF-01..05, F-NTF-07 (in-app), US-02 | lonceng, `/notifications`, `/dashboard`             |
| Peringkat Performa SLA Fakultas                   | F-TKU-08, F-DSB-01, US-02             | `/dashboard` (pelapor)                              |
| Header keamanan + batas umum 60/menit             | PRD 6.3                               | semua halaman                                       |

Yang **sengaja ditunda**: **email** notifikasi (bagian email F-NTF-01..04/07, email Ping F-NTF-06, retry 3x
PRD 6.4) — atas permintaan sendiri ("untuk sekarang gausah send ke email dulu"). Lihat bagian 4.4 untuk cara
menambahkannya nanti. Aturan tiket milik Staff nonaktif masih menunggu pembimbing (bagian 9).

---

## 1. Peta file baru

| Lokasi                                                        | Isi                                                                   |
| ------------------------------------------------------------- | --------------------------------------------------------------------- |
| `database/migrations/2026_10_21_100000_add_nip_to_users_table.php` | Kolom `users.nip` + memindah NIP lama dari kolom `nidn`          |
| `app/Http/Controllers/SuperAdmin/{Faculty,User}Controller.php` | Halaman Fakultas & Pengguna                                          |
| `app/Http/Controllers/SuperAdmin/DashboardController.php`     | Dashboard global (dulu kosong)                                        |
| `app/Http/Requests/SuperAdmin/{FacultyRequest,UpdateUserRoleRequest}.php` | Validasi form                                             |
| `app/Services/FacultyService.php`                             | Tambah/ubah fakultas, statistik per fakultas, daftar celah coverage   |
| `app/Services/AdminAppointmentService.php`                    | Tunjuk/cabut Admin (hanya `role` + `faculty_id`)                      |
| `app/Policies/FacultyPolicy.php`, `UserPolicy::changeRole()`  | Siapa boleh apa                                                       |
| `app/Services/Tickets/TicketNotifier.php`                     | SATU tempat semua notifikasi alur tiket                               |
| `TicketMetrics::facultyRanking()`, `components/sla-ranking`   | Peringkat SLA fakultas                                                |
| `app/Http/Middleware/SecurityHeaders.php`, `config/security.php` | Header keamanan                                                    |
| `resources/views/superadmin/*`                                | Tampilan Super Admin                                                  |
| `tests/Feature/{SuperAdmin,Notifications,Reports,Security}/*` | Tes baru M5                                                           |

Polanya tetap: **Controller** tipis → **Policy** (boleh/tidak) → **Service** (aturan PRD).

---

## 2. NIP untuk dosen

Sampai M4, kolom `users.nidn` ikut menyimpan NIP kalau SSO tidak memberi NIDN. Jalan, tapi namanya menyesatkan:
orang yang membaca database mengira isinya NIDN. Setelah diputuskan dosen memakai **NIP**, dibuat kolom sendiri.

**Kenapa migration BARU, bukan mengubah migration lama?** Migration lama sudah pernah dijalankan di Laragon (dan
nanti di server). Laravel mencatat migration yang sudah jalan di tabel `migrations`, jadi mengubah file lama tidak
berefek kecuali `migrate:fresh` (yang menghapus semua data). Migration baru cukup `php artisan migrate`.

Migration-nya juga **memindah data**: nilai 18 digit di kolom `nidn` (= NIP PNS) dipindah ke `nip`. Dikerjakan di
PHP (bukan `REGEXP` SQL) supaya sama di MySQL dan SQLite. `down()` mengembalikannya.

Aturan baru (`SsoProfile`, `SsoUserSynchronizer`):

| Data dari SSO                         | Disimpan                                             |
| ------------------------------------- | ---------------------------------------------------- |
| `nip`                                 | `users.nip`                                          |
| `nidn` (kalau memang dikirim)         | `users.nidn` — tidak lagi diisi NIP                  |
| tidak ada keduanya                    | yang diketik di form login dianggap NIP               |

Akun lama dicari dengan **semua** nomor (NIP, NIDN, yang diketik) di kolom `nip` **dan** `nidn`, jadi tidak ada
akun ganda. Login dengan NIDN tetap bisa (dikirim ke Bridge apa adanya). Di UI, semua teks "NIDN" diganti "NIP";
`User::identity()` memilih label yang tepat (NIP → NIM → NIDN untuk akun lama yang belum punya NIP).

---

## 3. Super Admin

### 3.1 Fakultas (US-17)

- Form: **Kode** (disimpan huruf besar, unik), **Nama**, **Slug** (kosong = kode huruf kecil, unik), **Deskripsi**, **Aktif**.
  Aturan unik saat ubah memakai `Rule::unique(...)->ignore($faculty)` supaya fakultas tidak "bentrok dengan dirinya sendiri".
- **Katalog standar otomatis**: tidak ada kode baru untuk ini! Hook `Faculty::created` (M1) sudah menyalin katalog.
  Yang baru: `FacultyService::create()` membungkus `Faculty::create()` dalam **transaksi**, jadi kalau penyalinan
  katalog gagal, fakultasnya ikut batal. Tes `test_new_faculty_gets_the_standard_catalog_from_the_form` membuktikannya
  lewat form sungguhan (sebelumnya hook ini hanya teruji dari seeder).
- **Nonaktif**: `TicketCategoryTree` (M2) sudah hanya menampilkan fakultas aktif, dan `assertSelectable()` menolak
  kiriman langsung. Tiket lama, Admin, dan Staff fakultas itu tetap ada.
- **Tidak ada hapus**: PRD 9.2 juga tidak punya `DELETE faculties`. Fakultas sudah punya kategori, tiket, dan akun;
  menghapusnya akan ikut menghapus banyak data (cascade). Nonaktifkan saja.
- Statistik per baris: jumlah Admin, Staff aktif (+ nonaktif), total tiket, dan **Kelompok tanpa Staff**.
  Dihitung dengan `withCount()` + satu query agregat `whereDoesntHave('staff', ...)`, bukan satu query per fakultas.

### 3.2 Pengguna (US-18)

- Daftar **seluruh** akun (read-only), cari NIM / NIP / email / nama, filter peran. Kotak cari di top bar ikut ke sini.
- **Tunjuk Admin** = pilih fakultas → `role = admin`, `faculty_id` = fakultas itu. Tombol yang sama memindah Admin
  ke fakultas lain. **Cabut** → `role = end_user`, `faculty_id = NULL`.
- `AdminAppointmentService` memakai `forceFill(['role' => ..., 'faculty_id' => ...])`: dari kode langsung terlihat
  bahwa HANYA dua kolom itu yang berubah. `user_type`, NIP/NIM, fakultas asal, password tetap (dites).
- `UserPolicy::changeRole()`: hanya untuk akun **Pelapor** atau **Admin**. Akun Staff ditolak (akun & spesialisasi
  Staff wewenang Admin Fakultas), akun Super Admin juga (supaya Super Admin tidak mengunci dirinya sendiri).
- Super Admin tetap **tidak bisa** membuat Staff, mengatur spesialisasi, atau kategori: route `/admin/*` dijaga
  `role:admin` (dites, 403).

### 3.3 Dashboard global (F-SPA-03, PRD 10.9)

Kartu Fakultas Aktif, Total Tiket, CSAT, SLA Compliance — memakai `TicketMetrics::summarize()` yang SAMA dengan
dashboard Admin, hanya query-nya tanpa `where faculty_id`. Itulah gunanya rumus KPI dipisah ke service di M4.
Ditambah ringkasan per fakultas dan daftar **celah coverage** (nama kelompoknya + jumlah tiket menunggu di sana).

---

## 4. Notifikasi in-app

### 4.1 Satu pintu: `TicketNotifier`

| Peristiwa                        | Penerima                  | Subjek                                   | PRD      |
| -------------------------------- | ------------------------- | ---------------------------------------- | -------- |
| tiket dibuat                     | pelapor                   | Tiket TKT-… berhasil dibuat              | F-NTF-01 |
| diambil Staff                    | pelapor                   | Tiket TKT-… sedang diproses              | F-NTF-02 |
| ditolak                          | pelapor                   | Tiket TKT-… ditolak (+ alasan)           | F-NTF-02 |
| selesai                          | pelapor                   | Tiket TKT-… selesai → klik = form rating | F-NTF-04 |
| Staff membalas (bukan internal)  | pelapor                   | Balasan baru dari Staff: TKT-…           | F-NTF-03 |
| pesan publik ke-20               | pelapor + Staff penangan  | Thread TKT-… mencapai batas 20 pesan     | F-NTF-07 |
| Ping Admin (sudah ada sejak M4)  | Staff                     | Ping dari Admin …                        | F-NTF-06 |

Dipanggil dari `TicketService`, `TicketWorkflowService`, dan `CommentService`, **setelah** transaksi selesai dan
**di luar** blok `try/catch` yang menghapus berkas. Kenapa? Kalau notifikasi gagal di dalam blok itu, lampiran
tiket yang SUDAH tersimpan akan ikut dihapus. Setiap pemanggilan juga dibungkus `rescue()`: error dicatat di log,
tapi tiket tetap tersimpan (tes `test_notification_failure_does_not_cancel_the_ticket`).

Klik ganda "Ambil Tiket" tidak membuat dua notifikasi: notifikasi hanya ditulis kalau UPDATE bersyarat benar-benar menang.

### 4.2 Di dashboard pelapor (US-02)

- Kotak biru **Notifikasi baru (n belum dibaca)**, mirip kotak "Teguran dari Admin" milik Staff.
- Badge **Balasan baru** di baris Tiket Terbaru (dikenali dari awalan subjek `TicketNotifier::REPLY_SUBJECT`).
- Membuka tiket = notifikasi tiket itu dianggap dibaca (`Notification::markTicketRead()`, sudah ada sejak M4 untuk Staff).
- Notifikasi "selesai" langsung membuka form rating (`NotificationController::ticketUrl()` menambah `#rating`).

Kotak "Teguran dari Admin" di dashboard Staff sekarang hanya mengambil notifikasi berawalan "Ping dari Admin",
karena lonceng Staff juga bisa berisi notifikasi batas thread.

### 4.3 Kenapa tetap tabel `notifications` versi PRD?

Sama seperti M4: tabel ini mengikuti ERD PRD (bukan tabel bawaan Laravel), jadi baris in-app ditulis lewat model
`App\Models\Notification`. Tidak ada tabel atau kolom baru.

### 4.4 Email: ditunda, tapi gampang ditambah

Semua notifikasi lewat satu method `TicketNotifier::notify()`. Kalau nanti email diaktifkan:

1. `php artisan make:notification TicketResolved` → `implements ShouldQueue`, `public $tries = 3`, `backoff()`
   (PRD 6.4), isi `toMail()` berbahasa Indonesia.
2. Panggil `$user->notify(new ...)` di `notify()` (model `User` sudah memakai `RoutesNotifications`).
3. Email Ping: listener baru untuk event `TicketPinged` (lihat komentar di `app/Events/TicketPinged.php`).
4. `.env`: `MAIL_MAILER=smtp` + akun SMTP (Mailtrap untuk dev, port 587 STARTTLS), lalu jalankan
   `php artisan queue:work` supaya email di antrean benar-benar terkirim.

---

## 5. Peringkat Performa SLA Fakultas (F-TKU-08)

`TicketMetrics::facultyRanking()`:

- **Hanya tiket Selesai** (Ditolak tidak dihitung, PRD 8.2), **hanya fakultas aktif**, **hanya agregat per
  fakultas** — tidak ada rincian kategori, jadi "Integritas Dosen" tidak pernah terlihat terpisah.
- Dibandingkan dengan `sla_target_hours` **snapshot** tiket itu, bukan SLA kategori sekarang.
- Diurutkan dengan **rasio** = waktu penyelesaian ÷ target. Kenapa bukan jam rata-rata saja? Target tiap kategori
  beda (KRS 24 jam, Integritas Dosen 120 jam). Fakultas yang banyak menerima tiket bertarget panjang akan terlihat
  "lambat" padahal selalu tepat waktu. Rasio membuat perbandingannya adil.
- Warna: **hijau "Cepat"** (rasio ≤ 0,75), **kuning "Sesuai target"** (≤ 1), **merah "Lambat"** (> 1). Juga
  ditampilkan rata-rata waktu dan % tepat SLA. Fakultas tanpa tiket Selesai di bawah, tanpa nomor.
- **Cache 10 menit** (`Cache::remember`): angkanya sama untuk semua pelapor, jadi tidak perlu dihitung ulang setiap
  kali dashboard dibuka. Hasilnya array biasa (bukan model) supaya aman disimpan di cache database.

---

## 6. Keamanan (PRD 6.3)

### 6.1 Header keamanan — `SecurityHeaders`

| Header                     | Isi                                    | Gunanya                                           |
| -------------------------- | -------------------------------------- | ------------------------------------------------- |
| `Content-Security-Policy`  | `default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; …` | browser hanya memuat aset dari server sendiri; script inline/asing ditolak |
| `X-Frame-Options`          | `SAMEORIGIN`                           | halaman tidak bisa dibingkai situs lain (clickjacking) |
| `X-Content-Type-Options`   | `nosniff`                              | browser tidak menebak jenis file                  |
| `Referrer-Policy`          | `strict-origin-when-cross-origin`      | URL lengkap (berisi nomor tiket) tidak bocor ke situs lain |
| `Permissions-Policy`       | kamera/mikrofon/lokasi dimatikan       |                                                   |
| `Strict-Transport-Security`| hanya untuk request HTTPS              | browser selalu memakai HTTPS                      |

Hal yang perlu diperhatikan supaya CSP **tidak merusak** aplikasi:

- **Alpine.js** versi standar menjalankan isi `x-data`/`@click` dengan `new Function()` → butuh `'unsafe-eval'`.
- `@fonts` menulis `<style>@font-face</style>` dan grafik memakai `style="width: ..%"` → style butuh `'unsafe-inline'`.
  Script inline tetap **tidak** diizinkan (memang tidak ada yang memakai).
- **`npm run dev`**: aset dimuat dari server Vite (`http://[::1]:5173` di Laragon) + websocket untuk auto-refresh.
  Middleware membaca file `public/hot` dan menambahkan alamat itu otomatis, jadi CSP tidak perlu dimatikan saat development.
- Halaman error debug Laravel (APP_DEBUG=true, error 500) memakai script inline, jadi CSP tidak dipasang di sana.
- Semua diatur di `config/security.php` / `.env`: `CSP_REPORT_ONLY=true` (hanya lapor di Console, tidak memblokir),
  `CSP_ENABLED=false`, `CSP_EXTRA_SOURCES="https://..."` untuk sumber luar baru.

Sudah dicoba di browser sungguhan (build produksi): tidak ada error CSP di Console dan Alpine berjalan.

### 6.2 Batas umum 60 request/menit

Limiter `authenticated` dipasang di grup `web` (`bootstrap/app.php`): 60 request/menit **per akun** yang login.
Per akun, bukan per IP, karena Wi-Fi kampus berbagi IP. Tamu tidak dibatasi limiter ini (halaman login punya
limiter sendiri sejak M1). Limiter khusus lama (`tickets` 30, `pings` 20, `login`) tetap berlaku.

---

## 7. Data demo baru (`php artisan migrate:fresh --seed`)

| Data                                                         | Untuk mencoba                                        |
| ------------------------------------------------------------ | ---------------------------------------------------- |
| Dosen demo memakai NIP (`198804122015031001`, dll.)           | label NIP, pencarian NIP di menu Pengguna            |
| Ahmad: notifikasi "sedang diproses" (dibaca) + "Balasan baru dari Staff" (belum dibaca) untuk tiket proyektor | kotak Notifikasi baru + badge Balasan baru |
| `superadmin@uinssc.test` / `password` (sudah ada sejak M1)    | semua halaman Super Admin                            |

---

## 8. Tes

| File                                              | Yang dites                                                     |
| ------------------------------------------------- | -------------------------------------------------------------- |
| `tests/Feature/SuperAdmin/FacultyManagementTest`  | akses per peran, statistik, katalog tersalin dari form, kode/slug unik, ubah, nonaktif hilang dari form tiket, menu |
| `tests/Feature/SuperAdmin/UserManagementTest`     | daftar, cari NIP/NIM/email/nama, tunjuk/pindah/cabut, hanya role+faculty berubah, Staff/Super Admin ditolak, tidak bisa buat Staff/kategori |
| `tests/Feature/SuperAdmin/SuperAdminDashboardTest`| metrik global dengan data terkontrol, per fakultas, celah coverage |
| `tests/Feature/Notifications/InAppNotificationTest` | tiap peristiwa, link rating, klik ganda, catatan internal, batas thread, dashboard pelapor, notifikasi gagal |
| `tests/Feature/Reports/FacultySlaRankingTest`     | urutan & warna, Ditolak tidak dihitung, snapshot SLA, fakultas nonaktif, kategori sensitif, cache |
| `tests/Feature/Security/*`                        | header, isi CSP, server Vite dev, report-only, batas 60/menit per akun |
| `tests/Feature/Auth/DosenLoginTest` (+ tes SSO lain) | NIP di kolom `nip`, akun lama tidak dobel, migration memindah NIP |

Hasil: sebelum M5 **366 tes lulus**, sesudah M5 **416 tes lulus** (`php artisan test`).

Trik: `Notification::creating(fn () => throw ...)` membuat penulisan notifikasi gagal dengan sengaja, untuk membuktikan
tiket tetap tersimpan. `Carbon::setTestNow()` + `addSeconds(600)` membuktikan cache peringkat kedaluwarsa.

---

## 9. Yang sengaja ditunda / perlu keputusan

| Hal                                                        | Kapan / siapa | Catatan                                                     |
| ---------------------------------------------------------- | ------------- | ----------------------------------------------------------- |
| Email notifikasi (F-NTF-01..04/07 bagian email, email Ping F-NTF-06, retry 3x PRD 6.4) | nanti | ditunda atas permintaan; cara menambahkan di bagian 4.4. Butuh akun SMTP |
| Tiket milik Staff yang dinonaktifkan                       | pembimbing    | belum diubah; Ping tetap ditolak `NO_ACTIVE_STAFF`          |
| Contoh respons asli Bridge `/api/v2/profil` (dosen: field `nip`?) | pengelola SSO | sesuaikan `config/sso.php` bila nama field beda       |
| Hapus fakultas                                             | —             | sengaja tidak ada; pakai nonaktif                           |
