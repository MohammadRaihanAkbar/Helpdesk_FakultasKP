
# Catatan Belajar M2: Kategori Layanan, Staff, dan Tiket

Catatan ini lanjutan dari `fondasi.md`, `M1-tampilan.md`, dan `M1-perbaikan-bug.md`. Milestone 2 (PRD §12,
Minggu 3–5) membuat helpdesk **benar-benar bisa dipakai**: Admin Fakultas mengatur layanan dan staff, pelapor
membuat tiket, memantau statusnya, dan berdiskusi di thread.

Fitur Staff (antrean, ambil tiket, ubah status), Ping, laporan, rating CSAT, email, dan Super Admin **belum**
dibuat di sini (M3+). Yang sudah disiapkan untuk mereka: policy, service thread, dan helper sensitif.

| Fitur                                          | User story              | Halaman                                           |
| ---------------------------------------------- | ----------------------- | ------------------------------------------------- |
| Pengaturan Layanan (kategori + SLA)            | US-11, PRD 2.7          | `/admin/services`                                 |
| Kelola Staff                                   | US-12                   | `/admin/staff`                                    |
| Spesialisasi Staff + ringkasan cakupan         | US-13                   | `/admin/staff/{id}/specialties`                   |
| Buat Tiket (dropdown bertingkat, lampiran)     | US-03, US-05B           | `/tickets/new`, `/tickets/new?from={id}`          |
| Tiket Saya + detail + linimasa + thread        | US-04                   | `/tickets`, `/tickets/{id}`                       |
| Riwayat Tiket                                  | US-05 (tanpa rating)    | `/history`, `/history/{id}`                       |
| Dashboard Pelapor dengan data asli             | US-02 (tanpa peringkat SLA) | `/dashboard`                                  |

---

## 1. Peta file baru

| Lokasi                                              | Isi                                                                                 |
| --------------------------------------------------- | ----------------------------------------------------------------------------------- |
| `app/Exceptions/BusinessRuleException.php`          | Satu jenis error untuk semua pelanggaran aturan PRD (kode `STANDARD_CATEGORY_LOCKED`, `INVALID_CATEGORY`, ...) |
| `app/Services/CategoryManager.php`                  | Aturan Pengaturan Layanan (standar vs tambahan, induk sah, hapus/nonaktif)         |
| `app/Services/StaffSpecialtyService.php`            | Simpan spesialisasi, pilihan checkbox per audiens, ringkasan "Kelompok tanpa Staff" |
| `app/Services/Tickets/TicketCategoryTree.php`       | Pilihan kategori di form tiket + validasi `INVALID_CATEGORY`                         |
| `app/Services/Tickets/TicketService.php`            | Membuat tiket (validasi, lampiran, nomor, snapshot)                                 |
| `app/Services/Tickets/TicketNumberGenerator.php`    | Nomor `TKT-YYYYMM-XXXX` yang aman dari tabrakan                                     |
| `app/Services/Tickets/AttachmentStorage.php`        | Aturan & penyimpanan lampiran di disk privat                                        |
| `app/Services/Tickets/CommentService.php`           | Thread: batas 20 pesan, catatan internal, `first_responded_at`                      |
| `app/Policies/*`                                    | Siapa boleh apa: `CategoryPolicy`, `UserPolicy`, `TicketPolicy`, `AttachmentPolicy`, `ResolutionProofPolicy` |
| `app/Http/Requests/Admin/*`, `Tickets/*`            | Validasi format isian form                                                          |
| `app/Http/Controllers/Admin/*`, `EndUser/*`, `FileController.php` | Controller tipis: terima request → panggil service → tampilkan view |
| `app/Support/Format.php`                            | Format ukuran berkas ("240 KB") tanpa ekstensi `intl`                               |
| `resources/views/admin/services/*`, `admin/staff/*` | Halaman Admin                                                                       |
| `resources/views/enduser/tickets/*`, `enduser/history/*` | Halaman Pelapor                                                                |
| `resources/views/components/ui/confirm-form.blade.php`   | Tombol + dialog konfirmasi untuk aksi destruktif                               |
| `resources/views/components/ui/textarea.blade.php`       | Textarea + penghitung karakter                                                 |
| `resources/views/components/ticket-row.blade.php`, `coverage-summary.blade.php`, `page-header.blade.php` | Potongan tampilan yang dipakai ulang |
| `database/seeders/DemoCategorySeeder.php`           | Contoh kategori tambahan FITK                                                       |
| `tests/Feature/Admin/*`, `tests/Feature/Tickets/*`  | 120-an tes baru                                                                     |

Pola yang dipakai di semua fitur:

```
Request ─▶ Route (middleware auth + role) ─▶ FormRequest (format isian)
       ─▶ Controller ─▶ Gate::authorize(...)  (Policy: boleh atau 403)
                    ─▶ Service (aturan bisnis; melanggar → BusinessRuleException)
                    ─▶ redirect + pesan / view
```

---

## 2. Tiga lapis pemeriksaan: FormRequest, Policy, Service

Satu aksi bisa ditolak karena tiga alasan yang berbeda, dan masing-masing punya tempat sendiri:

| Lapis        | Pertanyaan                                   | Contoh                                                    | Hasil kalau gagal              |
| ------------ | -------------------------------------------- | --------------------------------------------------------- | ------------------------------ |
| FormRequest  | Apakah **format** isiannya benar?            | judul ≤ 150 karakter, SLA bilangan 1–720, file ≤ 5 MB      | 422 + pesan di bawah field     |
| Policy       | Apakah **orang ini** boleh menyentuh data ini? | admin FITK mengubah kategori FEBI, mahasiswa membuka tiket orang lain | 403                |
| Service      | Apakah **aturan bisnis PRD** terpenuhi?      | kategori standar dihapus, kategori bukan leaf, pesan ke-21 | 409/422 + kode error PRD       |

Kenapa dipisah? Supaya setiap aturan **hanya ditulis sekali** dan mudah dites. Controller jadi pendek, misalnya:

```php
public function destroy(Request $request, Category $category)
{
    Gate::authorize('manage', $category);        // Policy: kategori fakultas sendiri?
    $this->categories->delete($category);        // Service: standar? sudah dipakai tiket?
    return to_route('admin.services.index')->with('status', '...');
}
```

### BusinessRuleException: satu error, dua tampilan

PRD bab 9 menulis respons error seperti:

```json
{ "success": false, "error": { "code": "STANDARD_CATEGORY_LOCKED", "message": "Kategori standar tidak dapat dihapus. ..." } }
```

`BusinessRuleException::render()` dipanggil Laravel otomatis:

- request JSON (Postman, `Accept: application/json`) → JSON di atas + status 409/422;
- form biasa di browser → kembali ke halaman sebelumnya, pesan muncul sebagai error validasi
  di field terkait (mis. `category_id`) atau sebagai kotak merah di atas halaman.

Jadi saat API dibuat nanti, service yang sama langsung bisa dipakai.

Daftar kode yang sudah ada:

| Kode                       | Kapan                                                            | Status |
| -------------------------- | ---------------------------------------------------------------- | ------ |
| `STANDARD_CATEGORY_LOCKED` | hapus kategori standar / ubah nama-induk-audiensnya               | 409 / 422 |
| `CATEGORY_IN_USE`          | hapus kategori tambahan yang sudah dipakai tiket                  | 409    |
| `INVALID_PARENT`           | induk tidak sah (fakultas lain, tingkat 3, kelompok standar tanpa anak) | 422 |
| `INVALID_SPECIALTY`        | spesialisasi bukan kelompok aktif fakultas sendiri                | 422    |
| `INVALID_CATEGORY`         | kategori tiket bukan leaf / fakultas salah / nonaktif / audiens salah | 422 |
| `TOO_MANY_ATTACHMENTS`     | lampiran baru + lampiran lama > 3                                 | 422    |
| `THREAD_LIMIT_REACHED`     | pesan publik ke-21                                                | 409    |
| `THREAD_CLOSED`            | membalas tiket yang sudah Selesai/Ditolak                         | 409    |

---

## 3. Policy: "boleh atau tidak"

Policy adalah class berisi method yang menjawab ya/tidak. Laravel menemukan policy otomatis dari namanya:
`App\Policies\TicketPolicy` untuk model `App\Models\Ticket`.

```php
// TicketPolicy
public function view(User $user, Ticket $ticket): bool
{
    return match (true) {
        $user->isEndUser() => $ticket->reporter_id === $user->id,          // hanya tiket sendiri
        $user->isStaff()   => $this->isHandler(...) || $this->inStaffSpecialty(...),
        $user->isAdmin()   => $user->faculty_id === $ticket->faculty_id,   // fakultasnya sendiri
        default            => false,                                       // Super Admin tidak menangani tiket
    };
}
```

Pemakaian: `Gate::authorize('view', $ticket)` di controller (gagal → 403), atau `Gate::allows(...)` untuk
menyembunyikan tombol di view.

| Policy                  | Method                  | Aturan singkat                                                                 |
| ----------------------- | ----------------------- | ------------------------------------------------------------------------------ |
| `CategoryPolicy`        | `manage`                | admin & `faculty_id` sama                                                      |
| `UserPolicy`            | `manageStaff`           | admin, target ber-peran staff, `faculty_id` sama                               |
| `TicketPolicy`          | `view`, `reply`, `viewInternalNotes`, `viewReporterIdentity`, `recreate` | lihat komentar di file |
| `AttachmentPolicy`      | `download`              | boleh lihat tiketnya; lampiran catatan internal hanya untuk staff/admin         |
| `ResolutionProofPolicy` | `download`              | boleh lihat tiketnya                                                            |

### Kategori sensitif "Integritas Dosen" (PRD 2.7 aturan 10)

ERD tidak punya kolom "sensitif", jadi penandanya **nama kategori standar** (`CategoryCatalogService::SENSITIVE_GROUPS`).
Aman, karena nama kategori standar dikunci dan kategori tambahan tidak boleh memakai nama yang sama (UNIQUE).

`TicketPolicy::viewReporterIdentity()` sudah siap untuk halaman staff/admin (M3–M4):

| Yang melihat                                         | Tiket biasa | Tiket Integritas Dosen |
| ---------------------------------------------------- | :---------: | :--------------------: |
| Pelapor sendiri                                      | ✅          | ✅                     |
| Staff penangan (`handler_id`)                        | ✅          | ✅                     |
| Admin Fakultas tujuan                                | ✅          | ✅                     |
| Staff lain dengan spesialisasi kelompok itu (antrean) | ✅          | ❌ (nama disamarkan)    |
| Admin fakultas lain, pelapor lain                    | ❌          | ❌                     |

Di view M3 cukup: `@can('viewReporterIdentity', $ticket) {{ $ticket->reporter->name }} @else Identitas dirahasiakan @endcan`.

---

## 4. Pengaturan Layanan (US-11)

`CategoryManager` menjaga aturan PRD 2.7:

| Aturan                                                   | Di mana                                       |
| -------------------------------------------------------- | --------------------------------------------- |
| Standar: hanya SLA, deskripsi, Aktif                     | `update()` + `guardStandardFields()`          |
| Standar tidak bisa dihapus                               | `delete()` → `STANDARD_CATEGORY_LOCKED`       |
| Induk = kelompok tambahan / standar yang sudah punya anak | `resolveParent()`                            |
| Maksimal 2 tingkat                                        | `resolveParent()` (induk harus `parent_id NULL`, kelompok yang punya anak tidak bisa jadi anak) |
| Audiens wajib untuk kelompok, anak mewarisi              | `CategoryRequest` (`required_without:parent_id`) + `createExtra()` |
| SLA anak default = SLA induk                              | `createExtra()` + Alpine di form (terisi otomatis) |
| Hapus hanya kalau belum dipakai tiket                    | `Category::isUsedByTickets()` → `CATEGORY_IN_USE` |
| `faculty_id` selalu milik admin                          | diambil dari `auth()->user()`, bukan dari form |

**Status Off menurun ke anak.** Saat kelompok dinonaktifkan, kolom `is_active` anak-anaknya **tidak ditimpa**.
Anak tetap dianggap nonaktif karena induknya Off (`Category::isEffectivelyActive()` dan `isSelectable()`),
dan di halaman admin tampil "Nonaktif (ikut kelompok)". Keuntungannya: saat kelompok diaktifkan lagi, status
setiap anak kembali seperti sebelumnya (admin tidak perlu menyalakan satu per satu).

**Kenapa field terkunci tetap "dikenali" di FormRequest?** Kalau `name` tidak ada di `rules()`, Laravel membuangnya
dari `validated()`, sehingga percobaan mengganti nama kategori standar lewat Postman akan **diam-diam diabaikan**.
PRD minta ditolak dengan jelas, jadi field itu diterima (`sometimes`) lalu dicek di service: boleh dikirim asal
nilainya sama, kalau berubah → `STANDARD_CATEGORY_LOCKED`.

### Dialog konfirmasi tanpa library

`<x-ui.confirm-form>` memakai elemen `<dialog>` bawaan browser:

```blade
<button type="button" @click="$refs.dialog.showModal()">Hapus</button>
<dialog x-ref="dialog">
    <form method="POST" action="..."> @csrf @method('DELETE') ... <button type="submit">Ya, hapus</button> </form>
</dialog>
```

`showModal()` sudah mengurus latar gelap, tombol Esc, dan fokus keyboard. Form baru terkirim setelah tombol
konfirmasi di dalam dialog diklik.

---

## 5. Kelola Staff & Spesialisasi (US-12, US-13)

- **Buat staff:** `role = staff` dan `faculty_id` diisi controller dari admin yang login. Input `faculty_id`/`role`
  dari form diabaikan (tidak ada di `rules()`, jadi tidak ikut `validated()`). Password di-hash otomatis oleh cast `hashed`.
- **Nonaktifkan:** cukup `is_active = false`. Middleware `EnsureUserIsActive` (M1) langsung mengeluarkan staff itu
  pada request berikutnya, dan login berikutnya ditolak. Tiket yang sedang ia proses tidak berubah.
- **Jumlah tiket aktif** di tabel = tiket berstatus Diproses dengan `handler_id` staff itu (`withCount`).
- **Spesialisasi:** `StaffSpecialtyService::sync()` hanya menerima id **Kelompok Layanan aktif milik fakultas staff**.
  Pemetaan lama ke kelompok yang sedang nonaktif dibiarkan (checkbox-nya memang tidak tampil), supaya langsung
  berlaku lagi saat kelompok diaktifkan.
- **Pengelompokan checkbox** (PRD 10.6): Mahasiswa & Dosen (bersama) → Khusus Mahasiswa → Khusus Dosen →
  Khusus Alumni → Kategori Tambahan {kode}. Ditentukan dari audiens kelompok (`bucketOf()`).
- **Ringkasan cakupan:** untuk setiap kelompok aktif dihitung staff **aktif** yang memegangnya dan tiket Menunggu
  di kelompok itu. Kelompok tiket dihitung dengan `COALESCE(categories.parent_id, categories.id)` — rumus yang
  sama dengan `Ticket::forStaff()` di M1.

---

## 6. Buat Tiket (US-03, US-05B)

### Dropdown bertingkat dengan Alpine

Semua pilihan kategori **di-embed** sebagai JSON di halaman (`TicketCategoryTree::forUserType()`):

```js
{ 1: [ {id: 49, name: "Sistem Akademik & Perkuliahan", sla_hours: 72, children: [ {id: 51, name: "KRS belum di-ACC", sla_hours: 24}, ... ]}, ... ],
  4: [ ... FEBI ... ] }
```

Isinya sudah disaring server: hanya fakultas aktif, kelompok aktif yang audiensnya memuat jenis pelapor, dan anak
yang aktif. Kelompok yang semua anaknya Off ikut disembunyikan. Datanya kecil (± 6 fakultas × 6 kelompok), jadi
tidak perlu request tambahan setiap ganti fakultas.

Di form, Alpine menyimpan tiga nilai dan menghitung sisanya:

```html
<form x-data="{ tree: ..., faculty: '1', group: '', category: '',
               get groups()   { return this.tree[this.faculty] || [] },
               get children() { return this.selectedGroup ? this.selectedGroup.children : [] },
               get leafId()   { /* anak yang dipilih, atau kelompok itu sendiri kalau tanpa anak */ } }">
  <select x-model="faculty" @change="group = ''; category = ''">   ← ganti fakultas: reset 2 & 3
  <select x-model="group"   @change="category = ''">               ← ganti kelompok: reset 3
  <div x-show="children.length"> <select x-model="category"> </div> ← Jenis Masalah hanya kalau ada anak
  <input type="hidden" name="category_id" :value="leafId">         ← yang dikirim hanya kategori LEAF
```

`get groups()` disebut *getter*: dihitung ulang otomatis setiap `faculty` berubah.

### Jangan percaya browser: `INVALID_CATEGORY`

Isi form bisa diubah siapa pun lewat DevTools atau Postman. Karena itu `TicketCategoryTree::assertSelectable()`
memeriksa ulang di server:

```php
$valid = $category !== null
    && $category->faculty_id === (int) $facultyId   // milik fakultas tujuan
    && $category->faculty->is_active
    && $category->isSelectable()                    // leaf + aktif + induk aktif
    && $category->isVisibleTo($userType);           // audiens cocok (anak lewat induknya)
```

### Snapshot

`sla_target_hours` disalin dari kategori dan `reporter_type` dari `users.user_type` **saat tiket dibuat**.
Kalau admin mengubah SLA besok, tiket lama tidak ikut berubah (ada tesnya).

### Nomor tiket yang aman dari tabrakan

Dua mahasiswa menekan **Kirim** di detik yang sama. Kalau keduanya membaca "nomor terakhir = 0041" lalu sama-sama
menyimpan 0042, salah satunya gagal. Tiga lapis pengaman:

1. `TicketNumberGenerator::next()` dipanggil **di dalam transaksi** dan memakai `lockForUpdate()`
   (`SELECT ... FOR UPDATE` di MySQL): transaksi kedua menunggu sampai yang pertama selesai.
2. Kolom `ticket_number` **UNIQUE** di database.
3. Kalau tetap bentrok (mis. awal bulan, belum ada baris untuk dikunci), `TicketService` menangkap
   `UniqueConstraintViolationException` dan **mengulang** transaksi (maks. 5 kali). Ada tes yang mensimulasikannya.

Nomor urut **reset setiap bulan** (`TKT-202610-0001`, lalu `TKT-202611-0001`). Setelah 9999 tiket sebulan,
nomor lanjut ke 5 digit; pengurutannya memakai `LENGTH()` dulu supaya "10000" > "9999".

### Lampiran di disk privat

| Hal                    | Cara                                                                                           |
| ---------------------- | ---------------------------------------------------------------------------------------------- |
| Jenis file             | `mimetypes:image/jpeg,image/png,application/pdf` (cek **isi** file) **dan** `extensions:jpg,jpeg,png,pdf` (cek **nama**) |
| Ukuran / jumlah        | `max:5120` (KB) per file, `max:3` berkas                                                       |
| Tempat simpan          | disk `local` = `storage/app/private` — tidak punya URL publik                                  |
| Nama file di disk      | nama acak (`hashName`), nama asli hanya disimpan di kolom `file_name`                          |
| Unduh                  | `GET /attachments/{id}` → `AttachmentPolicy::download` → `Storage::download()` + `X-Content-Type-Options: nosniff` |
| Transaksi gagal        | file yang sudah tersimpan dihapus lagi (`AttachmentStorage::discard()`)                        |

Contoh yang ditolak (ada tesnya): file teks yang diganti nama `foto.png`, PDF asli bernama `surat.exe`, GIF, DOCX.

### Buat Ulang Tiket (`/tickets/new?from={id}`)

Hanya untuk tiket milik sendiri yang **Ditolak** dengan alasan diawali `Salah fakultas/kategori`
(`Ticket::REJECTION_WRONG_TARGET`). Form terisi fakultas, kelompok, jenis, judul, deskripsi, dan daftar lampiran
lama (dicentang). Saat dikirim, lampiran lama **disalin** (file fisiknya juga) ke tiket baru; jumlahnya ikut
dihitung dalam batas 3. Tiket lama tetap Ditolak. Tombol **Buat Ulang Tiket** sudah muncul di detail tiket Ditolak.

---

## 7. Tiket Saya, detail, dan thread (US-04)

- **Daftar** hanya Menunggu/Diproses (`Ticket::open()`), kategori ditulis "Kelompok › Jenis" (`Category::pathLabel()`).
  Tiket Menunggu menulis "Menunggu diambil Staff" tanpa hitung mundur.
- **Detail** menampilkan linimasa dari `Ticket::timeline()`: Dibuat → Diambil oleh {staff} → Respons pertama →
  Selesai/Ditolak. Langkah yang belum terjadi tertulis "Belum".
- `/tickets/{id}` untuk tiket terbuka, `/history/{id}` untuk tiket yang sudah ditutup (saling redirect), supaya
  menu yang menyala di sidebar sesuai.
- **Thread** (`CommentService::post()`):

```php
DB::transaction(function () use (...) {
    $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail(); // antre per tiket
    if (! $internal && $locked->publicComments()->count() >= Ticket::THREAD_LIMIT) {
        throw BusinessRuleException::threadLimitReached(20);                 // 409
    }
    ...
});
```

  Mengunci baris tiket membuat dua pesan ke-20 yang dikirim bersamaan diproses bergantian, jadi tidak bisa
  "kebobolan" jadi 21. Catatan internal tidak dihitung dan tetap bisa ditulis staff setelah thread penuh.
- Pelapor **tidak pernah** menerima catatan internal: controller hanya mengambil `is_internal_note = false` kalau
  `viewInternalNotes` tidak diizinkan, dan lampiran di catatan internal juga ditolak `AttachmentPolicy`.
- Pesan staff diberi label **Staff** dan warna biru muda; pesan sendiri tertulis "Anda".

---

## 8. Data demo (`php artisan migrate:fresh --seed`)

| Seeder                | Isi M2                                                                                     |
| --------------------- | ------------------------------------------------------------------------------------------ |
| `DemoCategorySeeder`  | Kategori tambahan FITK "Laboratorium Microteaching" + 2 Jenis Masalah (dibuat lewat `CategoryManager`) |
| `UserSeeder`          | 6 staff: 5 FITK (Akademik, Umum, Alumni, Dekanat = Integritas Dosen, Cuti = nonaktif) + 1 FEBI |
| `DemoTicketSeeder`    | 8 tiket konsisten (lihat tabel di komentar file), lampiran & bukti berupa **file asli** di disk privat |

`DatabaseSeederTest::test_demo_tickets_follow_the_business_rules` memeriksa bahwa setiap tiket demo: kategorinya
leaf, audiens cocok, snapshot benar, nomor sesuai bulan dibuat, dan handler-nya memang punya spesialisasi itu.

---

## 9. Tes

| File                                         | Yang dites                                                            |
| -------------------------------------------- | --------------------------------------------------------------------- |
| `tests/Feature/Admin/ServiceSettingsTest.php` | pohon, standar terkunci, CRUD tambahan, induk sah, SLA default, hapus/nonaktif, lintas fakultas |
| `tests/Feature/Admin/StaffManagementTest.php` | buat staff, validasi, daftar, aktif/nonaktif, lintas fakultas         |
| `tests/Feature/Admin/StaffSpecialtyTest.php`  | checkbox per audiens, sensitif, simpan, id tidak sah, cakupan          |
| `tests/Feature/Tickets/CreateTicketTest.php`  | form, filter audiens, snapshot, `INVALID_CATEGORY`, lampiran, Buat Ulang |
| `tests/Feature/Tickets/TicketNumberTest.php`  | urutan per bulan, > 9999, percobaan ulang saat bentrok                 |
| `tests/Feature/Tickets/MyTicketsTest.php`     | Tiket Saya, detail + linimasa, riwayat, otorisasi                      |
| `tests/Feature/Tickets/ThreadTest.php`        | balasan, label Staff, catatan internal, batas 20, tiket ditutup        |
| `tests/Feature/Tickets/AttachmentDownloadTest.php` | unduh berizin, disk privat, file hilang = 404                      |
| `tests/Feature/Tickets/SensitiveReporterTest.php`  | identitas pelapor Integritas Dosen                                 |
| `tests/Feature/View/EndUserDashboardTest.php` | 4 kartu & tiket terbaru dari data asli                                 |

Trik yang dipakai:

- `Storage::fake('local')` supaya tes tidak menulis ke `storage/app/private` sungguhan.
- `UploadedFile::fake()->image('foto.png')` membuat PNG asli. Untuk menguji **isi** file, tes membuat file sungguhan
  (`new UploadedFile($path, 'foto.png', null, null, true)`), karena file palsu dari `fake()->create()` menebak MIME
  dari nama saja.
- `$this->travelTo(Carbon::create(2026, 10, 31))` untuk menguji pergantian bulan nomor tiket.
- `$this->app->instance(TicketNumberGenerator::class, ...)` mengganti generator dengan versi "nakal" untuk
  mensimulasikan tabrakan nomor.

Menjalankan sebagian: `php artisan test --filter=CreateTicketTest` atau `php artisan test tests/Feature/Tickets`.

---

## 10. Yang sengaja ditunda

| Hal                                     | Kapan  | Catatan                                                            |
| --------------------------------------- | ------ | ------------------------------------------------------------------ |
| Antrean, Ambil Tiket, ubah status, bukti | M3     | `TicketPolicy`, `CommentService`, `viewReporterIdentity` sudah siap |
| Rating CSAT                             | M3     | rating yang sudah ada sudah ditampilkan di detail                  |
| Email tiket dibuat / balasan baru       | M3–M4  | F-NTF-01..03                                                       |
| Peringkat Performa SLA Fakultas         | M3–M4  | butuh tiket Selesai dari alur staff                                |
| Ringkasan operasional Admin, Ping, Laporan | M4  | dashboard admin sementara berisi pintasan + cakupan                |
