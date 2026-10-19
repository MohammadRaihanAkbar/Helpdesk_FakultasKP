# Catatan Belajar M4: Admin Fakultas — Monitoring, Ping, dan Laporan

Catatan ini lanjutan dari `M3-alur-staff.md`. Milestone 4 (PRD §12, Fase 3 "Management Layer") memberi
**Admin Fakultas** alat untuk mengawasi tim Staff tanpa ikut menangani tiket:

```
Dashboard (KPI + tiket menganggur) ─▶ Monitor Antrean (penanda SLA, filter) ─▶ Ping / Ingatkan Staff
        └─▶ Laporan & Analitik (grafik, performa Staff) ─▶ Unduh CSV / Excel
Staff: teguran muncul di lonceng + dashboard ─▶ klik ─▶ tiket (Ping tampil di linimasa)
```

| Fitur                                             | User story            | Halaman / endpoint                                   |
| ------------------------------------------------- | --------------------- | ---------------------------------------------------- |
| Dashboard Admin (kartu, KPI, tren, performa)      | US-10, F-DSB-03       | `/admin/dashboard`                                   |
| Monitor Antrean + detail tiket hanya-baca         | US-16, F-ADM-05       | `/admin/queue`, `/admin/tickets/{id}`                |
| Ping / Ingatkan Staff                             | US-14, F-ADM-06       | `POST /admin/tickets/{id}/ping`, `GET .../pings`     |
| Notifikasi in-app (teguran)                       | F-NTF-05/06           | `/notifications`, `PATCH /notifications/{id}/read`, `PATCH /notifications/read-all` |
| Laporan & Analitik + ekspor                       | US-15, F-ADM-07/08    | `/admin/reports`, `/admin/reports/export?format=csv\|xlsx` |
| Filter Riwayat Staff (rentang tanggal & kategori) | US-09                 | `/staff/history`                                     |

Yang **belum**: Super Admin, email notifikasi (termasuk email Ping), peringkat SLA antar-fakultas di dashboard pelapor (M5).

---

## 1. Peta file baru

| Lokasi                                                   | Isi                                                                     |
| -------------------------------------------------------- | ----------------------------------------------------------------------- |
| `app/Services/Reports/TicketMetrics.php`                 | Rumus KPI PRD 1.4, deret waktu grafik, distribusi kelompok/jenis pelapor, performa Staff |
| `app/Services/Reports/ReportFilters.php`                 | Membaca filter laporan dari URL + membuat query tiket fakultas admin    |
| `app/Services/Reports/TicketExport.php`                  | Kolom ekspor PRD US-15, tulis CSV & XLSX                                |
| `app/Services/Tickets/PingService.php`                   | Siapa penerima Ping, tulis `ping_logs` + notifikasi, jeda klik ganda    |
| `app/Events/TicketPinged.php`                            | "Peristiwa" Ping — tempat menempelkan email teguran di M5               |
| `app/Http/Controllers/Admin/{Dashboard,Queue,Ticket,Ping,Report}Controller.php` | Halaman Admin                                    |
| `app/Http/Controllers/NotificationController.php`        | Daftar notifikasi, tandai dibaca                                        |
| `resources/views/admin/*`, `notifications/index`         | Tampilan                                                                |
| `resources/views/components/charts/bars`, `charts/donut` | Grafik batang (HTML+CSS) dan donat (SVG), tanpa library                 |
| `resources/views/components/kpi-card`, `admin-ticket-row`, `ping-button` | Kartu angka, baris tiket admin, tombol + dialog Ping    |
| `app/Support/ChartPalette.php`                           | Warna grafik dari token tema                                            |
| `tests/Feature/AdminMonitoring/*`                        | 35 tes baru untuk M4 (+2 di StaffHistoryTest & DatabaseSeederTest)      |

Polanya tetap sama: **Controller** tipis → **Policy** (boleh/tidak) → **Service** (aturan PRD).

---

## 2. KPI: rumus dari kolom waktu tiket

PRD 1.4 menyebut beberapa metrik. Semuanya bisa dihitung dari kolom yang **sudah ada** di tabel `tickets`
(`created_at`, `assigned_at`, `resolved_at`, `sla_target_hours`, `status`) + `ratings.score`. Tidak ada kolom baru.

| Metrik                 | Rumus                                                                  | Target PRD |
| ---------------------- | ---------------------------------------------------------------------- | ---------- |
| Ticket Claim Rate      | tiket yang `assigned_at`-nya terisi ÷ semua tiket                      | ≥ 95%      |
| Rata-rata Time-to-Claim| rata-rata `assigned_at − created_at` (tiket yang sudah diambil)        | dipantau   |
| Ticket Completion Rate | tiket Selesai ÷ tiket yang sudah ditutup (Selesai + Ditolak)           | ≥ 90%      |
| SLA Compliance Rate    | tiket Selesai dengan `resolved_at ≤ created_at + sla_target_hours` ÷ tiket Selesai | ≥ 85% |
| Rata-rata Resolution Time | rata-rata `resolved_at − created_at` tiket Selesai                  | sesuai SLA |
| CSAT Score             | rata-rata `ratings.score`                                              | ≥ 4,0      |

Dua hal penting:

1. **Tiket Ditolak tidak dinilai SLA** (PRD 8.2 tabel 6). Jadi SLA Compliance dan Resolution Time hanya memakai tiket Selesai.
2. **"Semua tiket" = tiket yang MASUK pada periode itu.** Dashboard memakai "masuk bulan ini", laporan memakai
   periode filter. Dengan satu definisi ini, angka di kartu bisa dicocokkan dengan isi file ekspor.

Kenapa selisih waktu dihitung di PHP, bukan SQL?

```php
// MySQL:  AVG(TIMESTAMPDIFF(SECOND, created_at, assigned_at))
// SQLite: AVG((julianday(assigned_at) - julianday(created_at)) * 86400)
```

Fungsinya berbeda antara MySQL (Laragon) dan SQLite (test). Supaya satu kode jalan di keduanya, `TicketMetrics`
mengambil kolom yang perlu saja lalu menghitung di PHP:

```php
$claimed = $rows->filter(fn (Ticket $t) => $t->assigned_at !== null);
'claim_rate' => self::percent($claimed->count(), $rows->count()),
'avg_claim_seconds' => self::average($claimed->map(fn (Ticket $t) => self::seconds($t->created_at, $t->assigned_at))),
```

Untuk ribuan tiket per fakultas ini masih ringan. Angka yang belum ada datanya ditulis **null** dan tampil "–"
(bukan 0%, karena 0% berarti "semua gagal").

Garis bawah kartu: **hijau** bila memenuhi target PRD, **merah** bila belum.

---

## 3. Dashboard Admin (US-10)

- 5 kartu US-10: **Tiket Masuk** (bulan ini), **Menunggu di Antrean** & **Sedang Diproses** (saat ini),
  **Selesai Hari Ini**, **Ditolak Bulan Ini** (berdasarkan `resolved_at`).
- 5 KPI bulan ini (bagian 2).
- **Tiket Menganggur**: status Menunggu **dan** sudah melewati target SLA (merah) — persis definisi US-10.
  Tiap baris punya tombol Ping.
- **Tren 7 hari**: tiket masuk per hari (`created_at`) dan tiket selesai per hari (`resolved_at`).
- **Tiket per Kelompok Layanan** (donat), **Performa Staff bulan ini**, dan kotak **Cakupan spesialisasi /
  Kelompok tanpa Staff** dari M2.
- Menu "Monitor Antrean" di sidebar menampilkan jumlah tiket menganggur.

---

## 4. Monitor Antrean (US-16)

Bawaan: semua tiket **aktif** (Menunggu & Diproses) fakultas admin, **tertua di atas** (paling lama menganggur).

- Badge merah *Melewati SLA* / kuning *Mendekati SLA* memakai helper M3 `Ticket::slaState()`.
- Kolom **Waktu menunggu** (Menunggu) / **Umur tiket** (Diproses) = sejak `created_at`, karena SLA juga dihitung dari situ.
- Filter: status (aktif, per status, semua), penanda SLA, Kelompok/Jenis Masalah, jenis pelapor, Staff penangan,
  rentang tanggal dibuat, cari nomor/judul. Kotak cari di top bar mencari di **semua status**.
- Detail `/admin/tickets/{id}` **hanya baca**: data + identitas pelapor (termasuk kategori sensitif — Admin berhak,
  PRD 2.7 aturan 10), lampiran, thread **dengan catatan internal**, bukti, rating, linimasa + Ping.
  Tidak ada tombol ambil/balas/pindah/tutup (PRD 2.3). Route Staff juga dijaga `role:staff`, jadi Admin yang
  mencoba lewat Postman mendapat 403.

Filter "Melewati SLA" dihitung di PHP (bukan SQL) dengan alasan yang sama seperti KPI: sama persis dengan badge.

---

## 5. Ping / Ingatkan (US-14)

### Siapa penerimanya?

| Status tiket | Penerima                                                                                        |
| ------------ | ----------------------------------------------------------------------------------------------- |
| Menunggu     | **semua Staff aktif** fakultas itu yang spesialisasinya mencakup Kelompok Layanan tiket (= yang melihat tiket di antreannya) |
| Diproses     | **Staff penangan** saja (hanya dia yang bisa bertindak)                                         |
| Selesai/Ditolak | tidak bisa di-Ping → `409 TICKET_CLOSED`                                                     |

Kalau tidak ada penerima → `422 NO_ACTIVE_STAFF` dengan kalimat persis PRD:
*"Belum ada Staff untuk kelompok ini — tetapkan spesialisasi terlebih dahulu."* Di tampilan, tombol Ping
dinonaktifkan dengan kalimat yang sama.

### Apa yang disimpan?

```php
foreach ($recipients as $staff) {
    PingLog::create([...]);          // ping_logs: 1 baris per staff (PRD 8.2 tabel 12)
    Notification::create([...]);     // notifications: type = in_app, is_read = false
}
TicketPinged::dispatch(...);         // untuk email teguran di M5
```

Isi notifikasi mengikuti US-14: nomor tiket, judul, kategori, lama menunggu, pesan Admin. **Identitas pelapor
tidak disertakan**, aman untuk kategori sensitif.

### Batas spam

PRD US-14: *"Tidak ada batasan jumlah Ping"*. Jadi tidak ada kuota. Yang dibatasi hanya kecelakaan:

- **Klik ganda**: Ping ke tiket yang sama dalam **30 detik** ditolak `429 PING_TOO_SOON`. Tombol kirim juga
  dikunci setelah diklik.
- **Skrip/klik beruntun**: pembatas route `throttle:pings` = 20 Ping per menit per akun admin.

### Di sisi Staff

- Lonceng di top bar menampilkan jumlah notifikasi belum dibaca.
- Dashboard Staff punya kotak kuning **Teguran dari Admin** (hanya muncul bila ada yang belum dibaca).
- Klik teguran → ditandai dibaca → membuka tiket. Membuka tiketnya langsung juga menandai teguran tiket itu dibaca.
- Linimasa tiket menampilkan **Ping dari Admin** di urutan waktu yang benar (seperti mockup PRD 10.5), lengkap
  dengan pengirim → penerima dan pesannya. Pelapor tidak melihat baris Ping.

### Menyambung email di M5

```bash
php artisan make:listener SendPingEmail --event=TicketPinged
```

Isi `handle(TicketPinged $event)` dengan pengiriman email ke `$event->staff`, lalu catat baris `notifications`
type `email`. `PingService` tidak perlu diubah.

---

## 6. Laporan & Analitik (US-15)

Filter (`ReportFilters`): **periode** 7 hari / 30 hari (bawaan) / rentang sendiri (maks. 1 tahun; tanggal terbalik
ditukar), **Kelompok atau Jenis Masalah**, **Staff penangan**, **status**, dan satuan grafik (hari/minggu/bulan).
Id kategori/staff milik fakultas lain diabaikan.

Isi halaman: kartu KPI, volume tiket masuk, distribusi **jenis pelapor** (dari snapshot `reporter_type`), donat
**Kelompok Layanan** dengan rincian **Jenis Masalah** (klik untuk membuka — elemen `<details>` bawaan HTML),
performa per Staff (ditangani, selesai, ditolak, rata-rata penyelesaian, SLA Compliance), dan tabel tiket.

### Grafik tanpa library

- **Batang**: deretan `<div>` yang tingginya `nilai ÷ nilai terbesar × 100%`. Angka persis ada di `title`
  (muncul saat kursor diarahkan) dan di tabel tersembunyi untuk pembaca layar.
- **Donat**: `<circle>` SVG dengan `pathLength="100"`. Setiap potongan = garis lingkaran sepanjang persentasenya
  (`stroke-dasharray="30 70"`) yang digeser (`stroke-dashoffset`) setelah potongan sebelumnya.

Tidak ada CDN dan tidak ada JavaScript tambahan, jadi halaman tetap cepat dan tidak bisa error di browser.

### Ekspor CSV & Excel

Kolom **persis** PRD US-15 (konstanta `TicketExport::HEADERS`):

```
No Tiket, Pelapor, Jenis Pelapor, Kelompok Layanan, Jenis Masalah, Status, Tanggal Dibuat, Tanggal Diambil,
Target SLA, Tanggal Selesai/Ditolak, Rating, Handler (Staff), Bukti Penyelesaian
```

- Filter ekspor = filter halaman (tombol unduh membawa query string yang sama).
- Tanggal ditulis `2026-10-05 14:20` (mudah diurutkan Excel), Target SLA `72 jam`, Jenis Masalah `-` bila
  kategori leaf-nya Kelompok tanpa anak, Bukti = daftar nama berkas dipisah `; `.
- **CSV**: diawali BOM UTF-8 (agar "—" dan "›" terbaca di Excel), pemisah koma. Teks yang diawali `= + - @`
  diberi tanda petik satu supaya tidak dijalankan sebagai rumus Excel ("CSV injection").
- **Excel**: library [`openspout/openspout`](https://github.com/openspout/openspout) v4 — menulis baris demi baris
  (hemat memori) dan **tidak butuh ekstensi `intl`**. Butuh ekstensi `zip`, `xmlwriter`, `dom` (bawaan Laragon;
  cek menu PHP → Extensions bila unduhan Excel gagal).
- Data dibaca per 200 tiket (`lazy(200)`), jadi ekspor ribuan tiket tidak menghabiskan memori.

---

## 7. Riwayat Staff: filter PRD US-09

PRD menyebut "filter berdasarkan **Rentang Tanggal dan Kategori**". Sekarang:

- **Ditutup dari / Sampai** (`resolved_at`, inklusif).
- **Kategori**: Kelompok Layanan atau Jenis Masalah (hanya dari kelompok yang pernah ia tutup).
- Filter lama tetap jalan: `?month=2026-10` diubah menjadi rentang 1–31 Oktober (dipakai tautan kartu dashboard),
  `?group=` tetap diterima.

---

## 8. Data demo baru (`php artisan migrate:fresh --seed`)

| Data                                                                 | Untuk mencoba                                     |
| -------------------------------------------------------------------- | ------------------------------------------------- |
| Tiket #13 *Legalisir transkrip untuk melamar CPNS* — Menunggu 100 jam (SLA 72), sudah 2× di-Ping | tiket menganggur di dashboard, riwayat Ping |
| Ping ke *Nilai Bahasa Arab belum keluar* (belum dibaca)              | teguran di dashboard Staff Akademik               |
| ±95 tiket riwayat (Selesai/Ditolak) 5 bulan terakhir di FITK & FEBI  | grafik, KPI, performa staff, ekspor               |
| 7 pelapor tambahan (`siti.aminah@…`, `hendra.saputra@…`, dst.)        | pemilik tiket riwayat (akun demo lama tidak berubah) |

Data riwayat dibuat "acak" dengan `mt_srand(2026)`, jadi hasilnya **selalu sama** setiap seeding: ±80% selesai
dalam SLA, ±12% ditolak, ±65% dirating 2–5, sebagian lama di antrean dan pernah di-Ping. Setelah semua tiket dibuat,
nomor tiket diurutkan ulang per bulan menurut `created_at` (dua langkah, supaya kolom UNIQUE tidak bentrok).

---

## 9. Kode error baru

| Kode               | Kapan                                                        | Status |
| ------------------ | ------------------------------------------------------------ | ------ |
| `NO_ACTIVE_STAFF`  | Ping ke kelompok tanpa Staff aktif / penangan nonaktif        | 422    |
| `TICKET_CLOSED`    | Ping ke tiket Selesai/Ditolak                                 | 409    |
| `PING_TOO_SOON`    | Ping ke tiket yang sama < 30 detik                            | 429    |

Contoh Postman (perlu cookie login + CSRF, lihat `panduan-postman.md`):

```
POST  /admin/tickets/42/ping           {"message": "Mohon segera ditindaklanjuti."}   Accept: application/json
GET   /admin/tickets/42/pings          Accept: application/json
GET   /admin/reports/export?format=csv&period=custom&from=2026-09-01&to=2026-09-30
GET   /notifications                   Accept: application/json
PATCH /notifications/7/read            Accept: application/json
```

Respons Ping sama dengan contoh PRD 9.3: `{"success": true, "data": {"ticket_id", "pinged_staff": [{id, name}],
"pinged_at", "ping_log_ids"}, "message": "Notifikasi teguran berhasil dikirim ke 2 staff terkait."}`.

---

## 10. Tes

| File                                                  | Yang dites                                                     |
| ----------------------------------------------------- | -------------------------------------------------------------- |
| `tests/Feature/AdminMonitoring/AdminAccessTest.php`   | staff/pelapor/super admin → 403, tamu → login, Admin FITK tidak melihat/Ping tiket FEBI (halaman, monitor, laporan, CSV), id fakultas lain di filter diabaikan, admin tanpa fakultas, admin tidak bisa ambil/balas/tutup |
| `tests/Feature/AdminMonitoring/KpiTest.php`           | rumus KPI dengan data terkontrol (`Carbon::setTestNow`), null bila kosong, kartu dashboard, tiket menganggur, performa staff, tren 7 hari, deret minggu/bulan, distribusi |
| `tests/Feature/AdminMonitoring/MonitorQueueTest.php`  | daftar aktif + urutan + badge SLA + waktu menunggu, semua filter, detail hanya-baca (sensitif, catatan internal, bukti, rating), unduh bukti, angka menu |
| `tests/Feature/AdminMonitoring/PingTest.php`          | penerima Menunggu vs Diproses, `ping_logs` + notifikasi, JSON PRD, 422/409, jeda klik ganda, validasi pesan, event, teguran di dashboard/lonceng/linimasa, tandai dibaca, notifikasi orang lain → 404, riwayat Ping, throttle |
| `tests/Feature/AdminMonitoring/ReportExportTest.php`  | kolom & isi CSV, BOM, CSV injection, XLSX dibaca ulang dengan openspout, filter periode/kategori/staff/status di halaman & ekspor, KPI & grafik di halaman |
| `tests/Feature/Staff/StaffHistoryTest.php` (+1)       | filter rentang tanggal & kategori US-09                        |
| `tests/Feature/DatabaseSeederTest.php` (+1)           | data demo M4: riwayat ≥ 4 bulan, menganggur, Ping, nomor tiket urut |

Trik yang dipakai:

- `Carbon::setTestNow('2026-10-20 12:00')` mengunci "sekarang", jadi "bulan ini", "7 hari terakhir", dan
  "lewat SLA" selalu sama setiap tes dijalankan.
- `$response->streamedContent()` membaca isi unduhan CSV; untuk XLSX, berkasnya dibuka lagi dengan
  `OpenSpout\Reader\XLSX\Reader`.
- `Event::fake([TicketPinged::class])` memeriksa event tanpa menjalankan listener.

Menjalankan sebagian: `php artisan test tests/Feature/AdminMonitoring`.

---

## 11. Yang sengaja ditunda / perlu keputusan

| Hal                                                         | Kapan / siapa | Catatan                                              |
| ----------------------------------------------------------- | ------------- | ---------------------------------------------------- |
| Email teguran Ping & email notifikasi lain                  | M5            | listener untuk `TicketPinged`; butuh SMTP/Mailtrap   |
| Peringkat SLA antar-fakultas (dashboard pelapor)            | M5            | rumus SLA Compliance sudah ada di `TicketMetrics`    |
| Super Admin (fakultas, pengguna)                            | M5            |                                                      |
| Tiket milik Staff yang dinonaktifkan                        | pembimbing    | Ping ke tiket seperti ini ditolak (`NO_ACTIVE_STAFF`) dan Monitor Antrean menandai penangannya "(nonaktif)", tetapi aturan pemindahannya belum diputuskan |
