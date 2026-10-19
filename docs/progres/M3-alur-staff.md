# Catatan Belajar M3: Alur Staff Fakultas & Rating

Catatan ini lanjutan dari `M2-kategori-dan-tiket.md`. Milestone 3 (PRD §12, Fase 2 lanjutan) menyelesaikan
**perjalanan sebuah tiket dari awal sampai akhir**:

```
Pelapor buat tiket ─▶ masuk Antrean staff sesuai spesialisasi ─▶ staff "Ambil Tiket" (Diproses)
   ─▶ diskusi di thread + catatan internal ─▶ Selesai (wajib bukti) / Ditolak (wajib alasan)
   ─▶ pelapor memberi rating (Selesai) atau "Buat Ulang Tiket" (Ditolak: salah fakultas/kategori)
```

| Fitur                                             | User story          | Halaman                                     |
| ------------------------------------------------- | ------------------- | ------------------------------------------- |
| Dashboard Staff (6 kartu + daftar SLA)            | US-06, PRD 10.3     | `/staff/dashboard`                          |
| Antrean Tiket sesuai spesialisasi                 | US-07, F-TKS-01     | `/staff/queue`                              |
| Ambil Tiket (aman dari balapan)                   | US-07, F-TKS-02     | `PATCH /staff/tickets/{id}/assign`          |
| Tiket Saya + detail + thread + Catatan Internal   | US-08, F-TKS-03/04/08 | `/staff/tickets`, `/staff/tickets/{id}`   |
| Tutup tiket: Selesai (bukti) / Ditolak (alasan)   | US-08, F-TKS-05/07  | `PATCH /staff/tickets/{id}/status`          |
| Riwayat Tiket Staff                               | US-09, F-TKS-06     | `/staff/history`, `/staff/history/{id}`     |
| Rating CSAT                                       | US-05, F-TKU-07     | `POST /tickets/{id}/rating`                 |

Yang **belum**: Monitor Antrean & Ping Admin, laporan/ekspor (M4), Super Admin dan email notifikasi (M5).

---

## 1. Peta file baru

| Lokasi                                                  | Isi                                                                          |
| ------------------------------------------------------- | ---------------------------------------------------------------------------- |
| `app/Enums/TicketStatus.php` → `canTransitionTo()`      | Diagram status PRD 4.5 dalam satu fungsi                                     |
| `app/Models/Ticket.php` → `slaDeadline()`, `slaState()`, `slaMet()` | Hitungan SLA (merah/kuning, sesuai/melewati)                     |
| `app/Services/Tickets/TicketWorkflowService.php`        | Ambil tiket, tandai Selesai, tolak — aturan bisnis alur staff                |
| `app/Services/Tickets/RatingService.php`                | Rating sekali per tiket Selesai                                              |
| `app/Policies/TicketPolicy.php` → `claim`, `handle`, `rate` | Siapa boleh mengambil, mengelola, dan memberi rating                     |
| `app/Http/Requests/Staff/UpdateTicketStatusRequest.php` | Format isian tutup tiket + menyusun teks alasan penolakan                    |
| `app/Http/Requests/Tickets/StoreRatingRequest.php`      | Bintang 1–5 + komentar ≤ 500                                                 |
| `app/Http/Controllers/Staff/*`                          | Dashboard, Queue, Ticket, TicketComment, History                             |
| `app/Http/Controllers/EndUser/RatingController.php`     | Simpan rating                                                                |
| `resources/views/staff/*`                               | Halaman staff                                                                |
| `resources/views/tickets/_comments`, `_timeline`, `_rating` | Potongan tampilan yang dipakai halaman pelapor **dan** staff             |
| `resources/views/components/staff-ticket-row.blade.php`, `sla-badge`, `stars` | Baris tiket staff, badge SLA, bintang rating           |
| `tests/Feature/Staff/*`, `tests/Feature/Tickets/RatingTest.php`, `tests/Unit/SlaTest.php` | 63 tes baru                                    |

Polanya sama dengan M2: **FormRequest** (format) → **Policy** (boleh/tidak, 403) → **Service** (aturan PRD,
`BusinessRuleException` dengan kode baku).

---

## 2. Mesin status (PRD 4.5)

```
Menunggu ──(Ambil Tiket)──▶ Diproses ──(bukti)──▶ Selesai
                                     └─(alasan)─▶ Ditolak
```

Semua transisi yang sah ditulis **sekali** di enum:

```php
public function canTransitionTo(self $to): bool
{
    return match ($this) {
        self::Menunggu => $to === self::Diproses,
        self::Diproses => in_array($to, self::closedStatuses(), true), // Selesai atau Ditolak
        self::Selesai, self::Ditolak => false,                          // sudah ditutup: final
    };
}
```

Jadi tidak bisa mundur (Diproses → Menunggu), tidak bisa melompat (Menunggu → Selesai), dan tiket yang sudah ditutup
tidak bisa dibuka lagi. Pelanggaran → `409 INVALID_STATUS_TRANSITION`.

Kolom waktu yang diisi:

| Kejadian                          | Kolom yang terisi                          |
| --------------------------------- | ------------------------------------------ |
| Ambil Tiket                       | `status = diproses`, `handler_id`, `assigned_at` |
| Balasan publik **pertama** penangan | `first_responded_at` (sekali saja, di `CommentService` sejak M2) |
| Selesai / Ditolak                 | `status`, `resolved_at` (+ `rejection_reason` untuk Ditolak) |

---

## 3. Ambil Tiket yang aman dari balapan

Dua staff berspesialisasi sama melihat tiket yang sama dan menekan **Ambil** di detik yang sama. Cara naif:

```php
if ($ticket->status === 'menunggu') {      // keduanya membaca "menunggu"...
    $ticket->update([...handler_id...]);   // ...lalu keduanya menulis -> yang kedua menimpa yang pertama!
}
```

Cara yang dipakai: **UPDATE bersyarat**. Syaratnya ditaruh di `WHERE`, jadi database sendiri yang memeriksa:

```php
$won = Ticket::whereKey($ticket->id)
    ->where('status', 'menunggu')
    ->whereNull('handler_id')
    ->update(['status' => 'diproses', 'handler_id' => $staff->id, 'assigned_at' => now()]) === 1;
```

Satu perintah `UPDATE` dijalankan database secara utuh (atomik). Staff pertama mengubah **1 baris**. Saat UPDATE staff
kedua dijalankan, syarat `status = 'menunggu'` sudah tidak benar, jadi **0 baris** berubah → kita tahu ia kalah →
`409 TICKET_ALREADY_ASSIGNED`.

- Request JSON (Postman): `{"success": false, "error": {"code": "TICKET_ALREADY_ASSIGNED", ...}}` status 409.
- Browser: kembali ke antrean dengan kotak merah *"Tiket ini sudah diambil oleh staff lain. Silakan pilih tiket lain di antrean."*
- Staff yang sama menekan dua kali (klik ganda): dianggap berhasil, tidak error.

Untuk **menutup** tiket dipakai cara lain, `lockForUpdate()` (seperti thread di M2), karena ada beberapa langkah
(simpan bukti + ubah status) yang harus terjadi bersama dalam satu transaksi.

Tes balapan (`ClaimTicketTest::test_race_condition_only_one_staff_wins`) meniru dua staff yang **sama-sama sudah
membaca** tiket berstatus Menunggu (dua objek model "basi"), lalu memanggil `assign()` bergantian.

---

## 4. Siapa boleh apa (TicketPolicy)

| Aksi                                    | Method policy        | Aturan                                                               |
| --------------------------------------- | -------------------- | -------------------------------------------------------------------- |
| Melihat detail tiket                    | `view` (M2)          | staff penangan, atau staff fakultas yang sama + kelompok tiket termasuk spesialisasinya |
| Ambil Tiket                             | `claim`              | staff **aktif** + kelompok tiket termasuk spesialisasinya (status tidak dicek di sini) |
| Balas thread / Catatan Internal         | `reply` (M2)         | pelapor pemilik atau **staff penangan**                              |
| Tandai Selesai / Tolak                  | `handle`             | hanya **staff penangan** yang aktif                                  |
| Identitas pelapor (kategori sensitif)   | `viewReporterIdentity` (M2) | penangan & Admin Fakultas; staff lain melihat "Identitas dirahasiakan" |
| Rating                                  | `rate`               | pelapor pemilik tiket                                                |

Kenapa `claim` tidak mengecek status? Supaya staff yang **kalah balapan** tetap lolos policy dan mendapat pesan yang
jelas (409 "sudah diambil"), bukan 403 "tidak boleh" yang membingungkan.

Staff nonaktif: middleware `EnsureUserIsActive` (M1) langsung mengeluarkannya. Sebagai lapis kedua,
`Ticket::forStaff()` mengembalikan antrean kosong dan `claim`/`handle` menolak staff nonaktif.

---

## 5. Antrean & SLA

- Isi antrean = `Ticket::forStaff($staff)->where('status', 'menunggu')` — scope dari M1, tidak berubah.
- Urutan **FIFO** (`created_at` naik): tiket paling lama di atas, sesuai PRD US-07.
- Filter: Kelompok Layanan (hanya dari spesialisasinya — id kelompok lain lewat URL diabaikan), jenis pelapor,
  rentang tanggal dibuat, dan cari nomor/judul (scope baru `Ticket::search()` dan `Ticket::inGroup()`).

SLA dihitung dari **snapshot** `sla_target_hours`:

```php
public function slaDeadline(): Carbon { return $this->created_at->copy()->addHours($this->sla_target_hours); }
```

| Tiket       | Kondisi                                 | Tampilan                    |
| ----------- | --------------------------------------- | --------------------------- |
| Terbuka     | sekarang > batas                        | badge merah **Melewati SLA** |
| Terbuka     | sisa waktu ≤ 25% target                 | badge kuning **Mendekati SLA** |
| Terbuka     | selain itu                              | "Sisa ± X jam" (abu-abu)    |
| Selesai     | `resolved_at` ≤ batas                   | hijau **Sesuai SLA** (`sla_met = true`) |
| Selesai     | `resolved_at` > batas                   | merah **Melewati SLA** (`sla_met = false`) |
| Ditolak     | —                                       | tidak dinilai (PRD 8.2: hanya tiket Selesai yang dihitung) |

`sla_met` **tidak disimpan** di database (ERD tidak punya kolomnya); selalu dihitung ulang dari `created_at`,
`resolved_at`, dan `sla_target_hours`. Contoh: KRS (SLA 24 jam) dibuat 20 jam lalu → sisa 4 jam < 6 jam (25%) → kuning.

---

## 6. Menutup tiket: bukti & alasan wajib

### Selesai

- Form mengunggah `proofs[]` (JPG/PNG/PDF, ≤ 5 MB, ≤ 3) **bersamaan** dengan `status=selesai`. Aturan file sama persis
  dengan lampiran (`AttachmentStorage::rules('proofs')`) dan disimpan di `storage/app/private/proofs/...`.
- Tanpa bukti → **400** `PROOF_REQUIRED` *"Bukti penyelesaian wajib dilampirkan."* (pesan persis PRD 9.3).
  Di browser, input file juga `required`, jadi dialog konfirmasi tidak terbuka sebelum ada berkas.
- Bukti diunduh lewat `GET /proofs/{id}` (policy M2: siapa pun yang boleh melihat tiketnya, termasuk pelapor).

### Ditolak

Form berisi dropdown alasan baku (`Ticket::REJECTION_REASONS`) + keterangan:

| Pilihan                                       | Disimpan di `rejection_reason`                      |
| --------------------------------------------- | --------------------------------------------------- |
| Salah fakultas/kategori                       | `Salah fakultas/kategori` atau `Salah fakultas/kategori: <keterangan>` |
| Data pelapor tidak valid                      | idem (+ keterangan)                                  |
| Di luar layanan helpdesk fakultas             | idem                                                 |
| Tiket ganda (sudah dilaporkan di tiket lain)  | idem                                                 |
| Alasan lainnya                                | keterangannya saja (keterangan **wajib**)            |

Tanpa alasan → **400** `REJECTION_REASON_REQUIRED`. Untuk Postman boleh juga mengirim `rejection_reason` (teks
langsung, sesuai nama kolom PRD).

**Kenapa awalan teks penting?** Tombol **Buat Ulang Tiket** di sisi pelapor (M2) muncul kalau
`rejection_reason` **diawali** `Salah fakultas/kategori` (`Ticket::isRejectedForWrongTarget()`). Karena form staff
menyusun teksnya dengan pola `"{baku}: {keterangan}"`, awalan itu selalu utuh. Tes
`StatusChangeTest::test_wrong_target_rejection_enables_recreate_end_to_end` menguji seluruh jalurnya: staff menolak
→ pelapor melihat tombol → buat ulang ke FEBI → tiket baru masuk antrean staff FEBI, tiket lama tetap Ditolak.

### Dialog konfirmasi

Menutup tiket tidak bisa dibatalkan, jadi tombolnya membuka `<dialog>` dulu. Bedanya dengan `<x-ui.confirm-form>`
(M2): di sini isian (berkas, alasan) ada di form utama, jadi `<dialog>` diletakkan **di dalam** form, dan tombol
pemicunya memeriksa isian dulu:

```html
<form x-ref="resolveForm" ...>
    <input type="file" name="proofs[]" required>
    <button type="button" @click="$refs.resolveForm.reportValidity() && $refs.resolveDialog.showModal()">Tandai Selesai…</button>
    <dialog x-ref="resolveDialog">
        ... <button type="submit">Ya, tandai Selesai</button>   <!-- tombol submit milik form yang sama -->
    </dialog>
</form>
```

`reportValidity()` menampilkan pesan bawaan browser kalau isian wajib masih kosong dan mengembalikan `false`,
sehingga dialog tidak terbuka.

---

## 7. Thread dari sisi staff

- Sama dengan M2 (`CommentService`), ditambah kotak centang **Catatan Internal (Staff Only)** → `is_internal_note=1`.
- Catatan internal: latar kuning, tidak terlihat pelapor, tidak dihitung batas 20, dan tetap bisa ditulis setelah
  thread publik penuh (form otomatis berubah menjadi "Catatan internal").
- Staff lain yang berspesialisasi sama melihat thread dan catatan internal, tetapi **tidak** bisa menulis (403).
- Untuk Integritas Dosen yang belum diambil, nama pelapor di thread juga disamarkan ("Pelapor (identitas dirahasiakan)").
- Linimasa staff menampilkan baris **Ping dari Admin** (kalau ada) — pelapor tidak melihatnya (catatan PRD 10.5).

---

## 8. Rating CSAT (US-05)

- Form bintang muncul di detail tiket **Selesai** milik pelapor yang belum dinilai. Bintangnya adalah
  `<input type="radio" name="score">` yang disembunyikan (`sr-only`), jadi tetap bisa dipakai dengan keyboard;
  Alpine hanya mewarnai bintang saat di-hover/dipilih.
- `RatingService` menolak tiket yang bukan Selesai (`409 RATING_NOT_ALLOWED`) dan rating kedua
  (`409 RATING_ALREADY_EXISTS`). Lapis kedua: kolom `ratings.ticket_id` **UNIQUE** di database; kalau dua klik
  lolos bersamaan, error database ditangkap dan diubah menjadi 409 yang sama.
- Setelah dikirim: *"Terima kasih atas feedback Anda."* (teks PRD). Rating tampil di Riwayat pelapor (bintang),
  detail tiket, Riwayat & detail staff, dan kartu **Rata-rata Rating** di dashboard staff.

---

## 9. Dashboard Staff

| Kartu                  | Rumus                                                                 |
| ---------------------- | --------------------------------------------------------------------- |
| Antrean                | tiket Menunggu sesuai spesialisasi                                    |
| Sedang Saya Proses     | `handler_id = saya`, Diproses                                         |
| Selesai Hari Ini       | saya, Selesai, `resolved_at` hari ini                                 |
| Ditolak Bulan Ini      | saya, Ditolak, `resolved_at` bulan ini                                |
| Ditangani Bulan Ini    | saya, Selesai + Ditolak, `resolved_at` bulan ini (keterangan: "x selesai") |
| Rata-rata Rating       | rata-rata `ratings.score` dari tiket yang pernah saya tangani (kartu tambahan) |

Di bawahnya: maksimal 5 tiket **Mendekati/Melewati SLA** (dari antrean + tiket saya; merah dulu, lalu batas
terdekat), dan 5 tiket yang sedang saya proses. Setiap kartu bisa diklik menuju halaman terkait.

---

## 10. Data demo baru (`php artisan migrate:fresh --seed`)

Sekarang ada **12 tiket** (sebelumnya 8). Tambahan:

| #  | Tiket                                       | Untuk mencoba                                                   |
| -- | ------------------------------------------- | --------------------------------------------------------------- |
| 9  | UKT belum terverifikasi — Selesai, belum dirating, melewati SLA | pelapor memberi rating; badge merah di riwayat staff |
| 10 | Nilai Bahasa Arab — Menunggu 80 jam (SLA 72) | badge merah di antrean Staff Akademik                          |
| 11 | Ruang kuliah Tafsir — Diproses Staff Akademik, belum dibalas | balasan pertama mengisi `first_responded_at`; badge kuning |
| 12 | Jadwal mengajar (dosen) — Menunggu 38 jam (SLA 48) | badge kuning di antrean                                    |

`DatabaseSeederTest` kini juga memeriksa bahwa setiap tiket demo mengikuti alur PRD 4.5 (Menunggu tanpa penangan,
Selesai punya bukti, Ditolak punya alasan, rating hanya pada tiket Selesai, respons pertama setelah diambil).

---

## 11. Kode error baru

| Kode                         | Kapan                                                     | Status |
| ---------------------------- | --------------------------------------------------------- | ------ |
| `TICKET_ALREADY_ASSIGNED`    | tiket sudah diambil staff lain                            | 409    |
| `INVALID_STATUS_TRANSITION`  | transisi di luar diagram PRD 4.5                          | 409    |
| `PROOF_REQUIRED`             | Selesai tanpa bukti                                       | 400    |
| `TOO_MANY_PROOFS`            | lebih dari 3 berkas bukti                                 | 422    |
| `REJECTION_REASON_REQUIRED`  | Ditolak tanpa alasan                                      | 400    |
| `RATING_NOT_ALLOWED`         | rating untuk tiket yang belum Selesai                     | 409    |
| `RATING_ALREADY_EXISTS`      | rating kedua                                              | 409    |

Contoh Postman (perlu cookie login + CSRF, lihat `panduan-postman.md`):

```
PATCH /staff/tickets/12/assign            Accept: application/json
PATCH /staff/tickets/12/status            Accept: application/json   (multipart: _method=PATCH, status=selesai, proofs[]=@bukti.png)
PATCH /staff/tickets/12/status            {"status": "ditolak", "reason": "salah_tujuan", "reason_note": "milik FEBI"}
POST  /tickets/12/rating                  {"score": 5, "comment": "Cepat"}
```

> Kirim berkas lewat **POST** + `_method=PATCH`: PHP tidak membaca berkas pada request PATCH asli.

---

## 12. Tes

| File                                         | Yang dites                                                            |
| -------------------------------------------- | --------------------------------------------------------------------- |
| `tests/Feature/Staff/QueueTest.php`          | isi antrean (fakultas, kelompok, status), FIFO, badge SLA, filter, tanpa spesialisasi, nonaktif, sensitif, akses peran lain, angka menu |
| `tests/Feature/Staff/ClaimTicketTest.php`    | ambil tiket, JSON PRD, 409 + pesan ramah, balapan, klik ganda, otorisasi, tiket ditutup, identitas sensitif setelah diambil |
| `tests/Feature/Staff/StaffThreadTest.php`    | form penangan, staff lain hanya melihat, catatan internal, `first_responded_at` sekali, thread penuh |
| `tests/Feature/Staff/StatusChangeTest.php`   | Selesai + bukti, `sla_met`, bukti wajib (400), aturan file, alasan wajib (400), alasan baku, Buat Ulang end-to-end, transisi ilegal, hanya penangan, unduh bukti |
| `tests/Feature/Staff/StaffHistoryTest.php`   | riwayat milik sendiri, filter bulan/status/kelompok, bukti + rating + SLA |
| `tests/Feature/Staff/StaffDashboardTest.php` | angka 6 kartu, daftar SLA, staff tanpa spesialisasi, sensitif         |
| `tests/Feature/Tickets/RatingTest.php`       | form, sekali saja, JSON, validasi, hanya Selesai, hanya pemilik, tampil di staff |
| `tests/Unit/SlaTest.php`                     | batas 25%, tepat di batas, `sla_met`, format durasi                    |

Trik yang dipakai:

- `$ticket->fresh()` dua kali untuk meniru dua staff yang membaca data yang sama sebelum salah satunya menulis.
- `$this->travelTo(...)` / `$this->travel(5)->minutes()` untuk menguji waktu (respons pertama, klik ganda, kartu "hari ini").
- `$response->viewData('stats')` untuk memeriksa angka kartu tanpa mencari teks di HTML.

Menjalankan sebagian: `php artisan test tests/Feature/Staff`.

---

## 13. Yang sengaja ditunda

| Hal                                                  | Kapan | Catatan                                                     |
| ---------------------------------------------------- | ----- | ----------------------------------------------------------- |
| Email notifikasi (tiket dibuat, status berubah, balasan, selesai + link rating) | M5 | F-NTF-01..04; titik pemanggilannya nanti di `TicketWorkflowService` & `CommentService` |
| Monitor Antrean Admin + Ping                         | M4    | baris Ping sudah tampil di linimasa staff                   |
| Laporan (SLA Compliance, CSAT, Time-to-Claim)        | M4    | `slaMet()` dan `assigned_at` sudah siap dipakai             |
| Peringkat Performa SLA Fakultas di dashboard pelapor | M4    | butuh rumus agregat per fakultas                            |
