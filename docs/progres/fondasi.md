
# M1 — Fondasi: Catatan Belajar

Milestone 1 menyiapkan "rangka" aplikasi: model & relasi untuk 12 tabel, login (lokal + SSO), registrasi alumni,
pembatasan akses per peran, dan route dashboard per peran. Tampilannya (Blade + Tailwind) dibahas di `M1-tampilan.md`.

Catatan ini menjelaskan konsep Laravel yang dipakai, contoh file-nya, dan alur sebuah request. Cocok dipakai
sebagai bahan laporan KP.

---

## 1. PHP Enum (`app/Enums`)

**Masalah:** nilai seperti `'super_admin'` atau `'menunggu'` ditulis berulang di banyak tempat. Salah ketik satu huruf
tidak menimbulkan error, hanya bug diam-diam.

**Solusi:** *backed enum* PHP 8.1 — satu tipe dengan daftar nilai yang pasti.

```php
enum TicketStatus: string
{
    case Menunggu = 'menunggu';   // 'menunggu' = nilai di database
    case Diproses = 'diproses';
    ...
    public function label(): string { ... }      // teks untuk UI
    public function badgeColor(): string { ... } // kunci warna badge
}
```

Model meng-*cast* kolom ke enum (`'status' => TicketStatus::class`), sehingga:

```php
$ticket->status === TicketStatus::Menunggu;   // bukan 'menunggu'
$ticket->status->label();                     // "Menunggu"
```

| Enum                 | Kolom                                                                            | Tambahan                                                     |
| -------------------- | -------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| `Role`             | `users.role`                                                                   | `label()`, `homeRoute()` (tujuan redirect setelah login) |
| `UserType`         | `users.user_type`, `category_audiences.user_type`, `tickets.reporter_type` | `label()`                                                  |
| `TicketStatus`     | `tickets.status`                                                               | `label()`, `badgeColor()`, `isOpen()`                  |
| `NotificationType` | `notifications.type`                                                           | `label()`                                                  |

`badgeColor()` sengaja mengembalikan **kunci** (`warning`, `info`, `success`, `danger`), bukan kode warna: komponen
badge nanti yang menerjemahkan kunci itu ke warna tema.

---

## 2. Eloquent: model, cast, relasi, scope (`app/Models`)

### 2.1 Model & `#[Fillable]`

Satu tabel = satu model. Laravel 13 bisa menulis daftar kolom yang boleh diisi massal dengan *attribute* PHP:

```php
#[Fillable(['name', 'email', 'password', 'nim', ...])]
class User extends Authenticatable
```

Kolom di luar daftar ini diabaikan oleh `User::create($data)` (perlindungan *mass assignment*).

### 2.2 Cast

`casts()` mengubah nilai mentah database menjadi tipe PHP: `boolean`, `datetime` (objek Carbon), `integer`,
enum, dan `hashed` (password otomatis di-hash bcrypt saat diisi).

### 2.3 Timestamp yang tidak lengkap

Eloquent menganggap setiap tabel punya `created_at` dan `updated_at`. Tabel PRD tidak selalu begitu:

| Model                                                                                         | Kolom waktu          | Cara memberi tahu Eloquent   |
| --------------------------------------------------------------------------------------------- | -------------------- | ---------------------------- |
| `Comment`, `Attachment`, `ResolutionProof`, `Rating`, `StaffSpecialty`, `PingLog` | hanya `created_at` | `const UPDATED_AT = null;` |
| `Notification` (pakai `sent_at`), `CategoryAudience` (tanpa waktu)                      | tidak ada            | `#[WithoutTimestamps]`     |

Tanpa ini, insert akan error *"Unknown column 'updated_at'"*.

### 2.4 Relasi

| Jenis                  | Contoh                                  | Artinya                                                           |
| ---------------------- | --------------------------------------- | ----------------------------------------------------------------- |
| `belongsTo`          | `Ticket::reporter()`                  | tiket punya satu pelapor (`tickets.reporter_id → users.id`)    |
| `hasMany`            | `Ticket::comments()`                  | satu tiket punya banyak pesan                                     |
| `hasOne`             | `Ticket::rating()`                    | satu tiket maksimal satu rating                                   |
| `belongsToMany`      | `User::specialties()`                 | staff ↔ kelompok layanan lewat tabel pivot `staff_specialties` |
| relasi ke diri sendiri | `Category::parent()` / `children()` | Kelompok Layanan ↔ Jenis Masalah                                 |

Dua relasi ke tabel yang sama dibedakan dengan nama & foreign key: `reporter()` (`reporter_id`) dan `handler()`
(`handler_id`); `faculty()` (tempat bertugas) dan `originFaculty()` (fakultas asal).

`User` memakai trait `RoutesNotifications`, **bukan** `Notifiable`, karena `Notifiable` menambah relasi
`notifications()` untuk tabel notifikasi bawaan Laravel yang strukturnya berbeda dengan tabel `notifications` di PRD.
Catatan notifikasi PRD dibaca lewat `inAppNotifications()`.

### 2.5 Scope

*Scope* = potongan query yang diberi nama dan bisa dirangkai. Di Laravel 13 cukup tandai method dengan `#[Scope]`:

```php
#[Scope]
protected function topLevel(Builder $query): void
{
    $query->whereNull('parent_id');
}

// Pemakaian:
Category::topLevel()->active()->visibleTo(UserType::Dosen)->get();
```

Scope penting di M1:

- `Category::visibleTo(UserType)` — audiens hanya disimpan di kelompok, jadi Jenis Masalah dicek lewat **induknya**
  (`whereHas('audiences')` *atau* `whereHas('parent.audiences')`).
- `Ticket::forStaff($staff)` — tiket fakultas yang sama **dan** kelompoknya termasuk spesialisasi staff.
  Kelompok sebuah tiket dihitung dengan `COALESCE(categories.parent_id, categories.id)`: jika kategori tiket adalah
  Jenis Masalah ambil induknya, jika Kelompok tanpa anak ambil dirinya sendiri.
- `Ticket::open()` / `closed()` — status terbuka (menunggu, diproses) vs ditutup (selesai, ditolak).

Helper (bukan scope): `Category::group()`, `isLeaf()`, `isSelectable()`, `User::isStaff()`, `isAdmin()`, dst.

### 2.6 Event model

`Faculty::booted()` mendaftarkan fungsi yang berjalan otomatis setiap ada fakultas baru:

```php
static::created(fn (Faculty $faculty) => app(CategoryCatalogService::class)->seedForFaculty($faculty));
```

Jadi saat Super Admin menambah fakultas (M5), 12 kategori standar langsung tersalin (F-SYS-01).
Catatan: `DB::table(...)->insert()` (dipakai `FacultySeeder`) **tidak** memicu event model; karena itu
`CategoryCatalogSeeder` tetap ada untuk 6 fakultas awal.

---

## 3. Service class (`app/Services`)

Service = class biasa berisi aturan bisnis yang dipakai di lebih dari satu tempat, supaya controller dan seeder tetap
tipis.

| Service                                      | Dipakai oleh                                             |
| -------------------------------------------- | -------------------------------------------------------- |
| `CategoryCatalogService::seedForFaculty()` | `CategoryCatalogSeeder` dan event `Faculty::created` |
| `Auth\LoginService::attempt()`             | `LoginController@store`                                |
| `Sso\SsoUserSynchronizer::sync()`          | `LoginService`                                         |

Service tidak perlu di-`new` manual: tulis sebagai parameter dan Laravel menyuntikkannya (*dependency injection*):

```php
public function run(CategoryCatalogService $catalog): void   // di seeder
public function store(LoginRequest $request, LoginService $loginService)   // di controller
```

**Interface + binding.** `SsoClient` adalah interface; implementasinya dipilih di `AppServiceProvider::register()`
sesuai `SSO_DRIVER` (`BridgeSsoClient` atau `FakeSsoClient`). Kode lain cukup meminta `SsoClient` tanpa tahu
implementasi mana yang dipakai.

---

## 4. Middleware (`app/Http/Middleware`)

Middleware = penjaga pintu yang berjalan **sebelum** controller.

| Middleware                        | Dipasang di                      | Fungsi                                                                  |
| --------------------------------- | -------------------------------- | ----------------------------------------------------------------------- |
| `auth` (bawaan)                 | route yang wajib login           | tamu diarahkan ke `/login`                                            |
| `guest` (bawaan)                | `/login`, `/register/alumni` | user yang sudah login diarahkan ke dashboard perannya                   |
| `role` → `EnsureUserHasRole` | grup route per peran             | peran tidak cocok → **403**                                      |
| `EnsureUserIsActive`            | semua route web                  | akun dinonaktifkan saat masih login → logout paksa                     |
| `EnsureAlumniRegistrationKey`   | `/register/alumni`             | `ALUMNI_REGISTRATION_KEY` diisi tapi `?key=` salah → **404** |
| `throttle:6,1` (bawaan)         | POST registrasi alumni           | maksimal 6 request/menit                                                |

Pendaftaran alias & middleware global ada di `bootstrap/app.php`:

```php
$middleware->alias(['role' => EnsureUserHasRole::class]);
$middleware->web(append: [EnsureUserIsActive::class]);
```

Pemakaian di `routes/web.php`:

```php
Route::middleware(['auth', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', Staff\DashboardController::class)->name('dashboard');
});
```

---

## 5. Form Request (`app/Http/Requests/Auth`)

Validasi dipindah dari controller ke class khusus. Laravel menjalankannya **otomatis sebelum** method controller;
jika gagal, user dikembalikan ke form dengan pesan error dan isian lama (`old()`).

`AlumniRegistrationRequest`:

| Method                     | Isi                                                                                                                                      |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| `prepareForValidation()` | merapikan input (email huruf kecil, buang spasi)                                                                                         |
| `rules()`                | `email` unik, `nim` 8–20 digit & unik, `graduation_year` ≤ 2021, `origin_faculty_id` fakultas aktif, `password` dikonfirmasi |
| `messages()`             | pesan khusus: tahun ≥ 2022 atau NIM sudah ada → pesan PRD `ALUMNI_USE_SSO` (arahkan ke login SSO)                                    |
| `attributes()`           | nama field di pesan ("tahun lulus", bukan "graduation_year")                                                                             |

Pesan umum Bahasa Indonesia ada di `lang/id/validation.php` (aktif karena `APP_LOCALE=id`).
`:Attribute` (huruf A besar) membuat huruf pertama nama field kapital: "Tahun lulus wajib diisi."

`LoginRequest` juga mengurus **rate limiting**: maksimal 5 kali gagal per menit untuk pasangan login + IP
(`RateLimiter::hit()`, `tooManyAttempts()`, `clear()`).

---

## 6. Alur login SSO langkah demi langkah

Contoh: alumni lulus 2023 mengetik NIM `1910110045` dan password.

```
Browser ── POST /login ──▶ routes/web.php (middleware guest, throttle)
                           │
                           ▼
              LoginRequest  ── validasi field login & password
                           │
                           ▼
          LoginController@store
            1. $request->ensureIsNotRateLimited()          (≥ 5 gagal/menit → tolak)
            2. LoginService::attempt(login, password, remember)
                 │  login tidak mengandung "@" → jalur SSO
                 ▼
               SsoClient::authenticate()   ← implementasi dipilih AppServiceProvider (SSO_DRIVER)
                 │   BridgeSsoClient: POST /api/v2/portal/login → token
                 │                    GET  /api/v2/profil (Bearer token)
                 │   FakeSsoClient:   ambil config('sso.fake_users')['1910110045']
                 ▼
               SsoProfile::fromResponse()  ← pemetaan field dari config/sso.php
                 │   status "Lulus" → UserType::Alumni, graduation_year 2023, fakultas FITK
                 ▼
               SsoUserSynchronizer::sync()
                 │   cari users.nim = '1910110045' → perbarui nama, email, user_type, ...
                 │   (role & faculty_id tidak disentuh; akun baru diberi password acak)
                 ▼
               cek is_active → Auth::login($user, $remember) → simpan sso_token di session
            3. gagal di mana pun → ValidationException → hitungan rate limit +1 → kembali ke form
            4. berhasil → hapus hitungan, session()->regenerate() (anti session fixation)
            5. redirect ke route($user->role->homeRoute())  → /dashboard
```

Logout (`POST /logout`): panggil `SsoClient::logout($token)` (*best effort*), `Auth::logout()`,
`session()->invalidate()`, `regenerateToken()`, kembali ke `/login`.

File yang terlibat:

| File                                              | Peran dalam alur                                             |
| ------------------------------------------------- | ------------------------------------------------------------ |
| `routes/web.php`                                | route `login`, `login.store`, `logout`                 |
| `app/Http/Requests/Auth/LoginRequest.php`       | validasi + rate limit                                        |
| `app/Http/Controllers/Auth/LoginController.php` | menerima request, redirect                                   |
| `app/Services/Auth/LoginService.php`            | memilih jalur lokal / SSO, cek akun aktif, `Auth::login()` |
| `app/Providers/AppServiceProvider.php`          | binding `SsoClient` → Bridge/Fake                         |
| `app/Services/Sso/*`                            | klien SSO, DTO profil, sinkronisasi user, exception          |
| `config/sso.php`                                | URL, timeout, pemetaan field, akun simulasi                  |
| `config/auth.php`                               | `remember` 10080 menit (7 hari), kunci hidden link alumni  |
| `app/Enums/Role.php`                            | `homeRoute()` tujuan redirect                              |

Login **lokal** (ada `@`) sama, hanya langkah SSO diganti: cari user berdasarkan email, cocokkan dengan
`Hash::check()`.

---

## 7. Test (`tests/Feature`)

`php artisan test` menjalankan semua test. Database test memakai SQLite in-memory dan trait `RefreshDatabase`
(setiap test mulai dari database bersih). Bridge tidak pernah dihubungi: `Http::fake()` memalsukan responsnya dan
`Http::preventStrayRequests()` menggagalkan test jika ada request sungguhan.

Test logika sengaja tidak bergantung pada tampilan: middleware diuji langsung (`handle()`) atau lewat route
khusus test, login diuji lewat `POST /login` dan redirect-nya.

| File                                                     | Yang diuji                                                                                        |
| -------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| `Models/CategoryTest`                                  | katalog otomatis 12 kategori, `visibleTo`, aturan leaf, status On/Off bertingkat                |
| `Models/TicketScopeTest`                               | `forStaff`, `open`, `closed`, cast & relasi tiket                                           |
| `DatabaseSeederTest`                                   | seeder berjalan di SQLite, antrean staff demo, relasi tiket demo                                  |
| `Sso/SsoProfileMappingTest`, `Sso/FakeSsoClientTest` | pemetaan field profil, akun simulasi                                                              |
| `Sso/BridgeSsoClientTest`                              | request ke Bridge, error 401/5xx/timeout, logout best effort                                      |
| `Sso/SsoUserSynchronizerTest`                          | buat/perbarui user dari profil, role & fakultas penugasan tidak berubah, email pengganti          |
| `Auth/MiddlewareTest`                                  | `EnsureUserHasRole`, `EnsureUserIsActive`, `EnsureAlumniRegistrationKey` dipanggil langsung |
| `Auth/LoginServiceTest`                                | jalur lokal vs SSO, password salah, akun nonaktif                                                 |
| `Auth/RoleMiddlewareTest`                              | tamu → login, peran salah → 403, akun dinonaktifkan di tengah sesi                              |
| `Auth/LocalLoginTest`                                  | login email, redirect per peran, throttle, remember me 7 hari, akun demo, logout                  |
| `Auth/SsoLoginTest`                                    | login NIM/NIDN lewat form: akun baru, alumni ≥ 2022, Admin tetap Admin, SSO tidak bisa dihubungi |
| `Auth/AlumniRegistrationTest`                          | ≤ 2021 berhasil, ≥ 2022 & NIM ganda → pesan SSO, email ganda, kunci hidden link → 404         |

## 8. Perubahan pada kode awal

- Migration `ratings`: `ALTER TABLE ... ADD CONSTRAINT CHECK` dilewati di SQLite (tidak didukung SQLite), tetap dibuat di
  MySQL/MariaDB. Tanpa perbaikan ini `php artisan test` gagal.
- `CategoryCatalogSeeder`: logika & isi katalog dipindah ke `CategoryCatalogService`; seeder kini hanya memanggilnya.
- `config/app.php`: timezone `Asia/Jakarta`, locale `id`; `config/session.php`: lifetime 7 hari;
  `config/auth.php`: remember 7 hari + kunci hidden link.
- `resources/views/welcome.blade.php` (halaman bawaan Laravel) dihapus; `/` diarahkan ke `/login`.
