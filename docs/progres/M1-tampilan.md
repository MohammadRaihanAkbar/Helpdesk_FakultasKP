
# Catatan Belajar M1: Tampilan (Blade, Tailwind, Alpine)

Catatan ini lanjutan dari `M1-fondasi.md`. Kalau fondasi membahas "otak" aplikasi (model, SSO, middleware),
bagian ini membahas "wajah"-nya: layout, halaman login, registrasi alumni, dashboard kosong per peran,
dan halaman error. Gayanya mengikuti mockup **"A · Terang"** (`design/a-*.png`, pilihan M.Raihan): latar lembut
hijau-krem, kartu putih membulat dengan bayangan halus, banner dan tombol hijau bergradasi, tombol emas untuk
ajakan utama, motif bintang emas dari logo. Tanpa efek kaca (blur) supaya ringan di HP, dan belum ada mode gelap.

Daftar file yang dibahas:

| Lokasi                                                              | Isi                                                                           |
| ------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| `vite.config.js`                                                  | Vite + Tailwind + font Plus Jakarta Sans, Inter, JetBrains Mono (self-hosted) |
| `resources/css/app.css`                                           | Token tema (`@theme`), gradasi (`@utility`), gaya dasar, fokus keyboard   |
| `resources/views/components/ui/*`                                 | Komponen kecil: button, input, select, card, badge, alert, empty-state        |
| `resources/views/components/icon.blade.php`                       | Ikon SVG (Lucide)                                                             |
| `resources/views/components/layouts/*`                            | Layout `guest` (login), `app` (setelah login), `error`                  |
| `app/Support/Navigation.php`                                      | Menu per peran, bottom nav HP, kotak cari, kartu hijau sidebar                |
| `app/View/Components/LoginShowcase.php`                           | Komponen berkelas: angka di sisi kiri halaman login                           |
| `resources/views/auth/*`, `*/dashboard.blade.php`, `errors/*` | Halaman                                                                       |
| `tests/Feature/View/*`                                            | Tes tampilan                                                                  |

---

## 1. Komponen Blade

Komponen = potongan tampilan yang dipakai ulang, seperti fungsi tapi untuk HTML. File
`resources/views/components/ui/button.blade.php` otomatis bisa dipanggil sebagai `<x-ui.button>`
(titik = folder). Kita memakai **komponen anonim** (tanpa class PHP) karena isinya hanya tampilan. Pengecualian: `x-login-showcase` butuh data dari database, jadi dibuat
sebagai **komponen berkelas** (`app/View/Components/LoginShowcase.php` + view-nya).

### Props

```blade
@props(['variant' => 'primary', 'type' => 'button', 'href' => null, 'icon' => null])
```

`@props` mendaftarkan atribut yang dianggap "parameter" beserta nilai bawaannya. Pemanggilan:

```blade
<x-ui.button variant="secondary" icon="arrow-left" :href="route('login')">Kembali</x-ui.button>
```

Perhatikan titik dua: `:href="route('login')"` berarti nilainya **ekspresi PHP**; tanpa titik dua
(`href="..."`) nilainya string biasa.

### `$slot` dan slot bernama

Isi di antara tag pembuka dan penutup masuk ke `{{ $slot }}`. Kalau butuh lebih dari satu "lubang", pakai
slot bernama. Contoh di `empty-state`:

```blade
<x-ui.empty-state icon="ticket" title="Belum ada tiket">
    Tiket yang kamu buat akan muncul di sini.
    <x-slot:action>
        <x-ui.button href="...">Buat Tiket</x-ui.button>
    </x-slot:action>
</x-ui.empty-state>
```

Di dalam komponen, slot bernama dibaca sebagai variabel `$action` (cek dulu dengan `isset($action)`).

### `$attributes`

Atribut yang **bukan** props (mis. `class`, `id`, `autofocus`) terkumpul di `$attributes`.

- `$attributes->merge(['class' => '...'])` menggabungkan kelas bawaan dengan kelas tambahan dari pemanggil
  (jadi `<x-ui.button class="w-full">` tetap punya gaya tombol + `w-full`).
- `$attributes->class([...])` menambah kelas dengan syarat.
- `$attributes->except(['id'])` membuang atribut tertentu (dipakai di `input` karena `id` dipasang sendiri).

### Kenapa komponen?

Halaman login, registrasi, dan nanti form tiket memakai input yang sama: label, petunjuk, pesan error,
`aria-invalid`, `aria-describedby`. Kalau ditulis ulang di tiap halaman, satu perbaikan harus diulang di
banyak tempat. Dengan komponen cukup sekali.

Catatan kecil di `input`: kolom password **tidak pernah** diisi ulang dengan `old()` (keamanan), dan
`isset($errors)` dipakai supaya komponen tetap aman dirender di luar request biasa (mis. di tes).

---

## 2. `@vite` dan `@fonts`

Di `<head>` layout:

```blade
@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

- **`@vite`**: saat `npm run dev`, menunjuk ke server Vite (perubahan langsung terlihat, *hot reload*).
  Setelah `npm run build`, menunjuk ke file hasil build di `public/build` yang namanya ber-hash
  (supaya browser tidak memakai cache lama).
- **`@fonts`**: dari laravel-vite-plugin. Di `vite.config.js` kita minta tiga font: `bunny('Plus Jakarta Sans', { weights: [800] })` untuk judul,
  Inter 400/600/700 untuk isi, dan JetBrains Mono 600 untuk nomor tiket.
  File font **diunduh saat build** dari fonts.bunny.net lalu disimpan di `public/build`. Artinya:
  - browser pengguna mengambil font dari server kita sendiri (tidak ada permintaan ke pihak ketiga);
  - `npm run build`/`npm run dev` di laptop kamu butuh internet **sekali** (setelah itu tersimpan di cache).
    Direktif ini juga membuat variabel CSS `--font-plus-jakarta-sans`, `--font-inter`, `--font-jetbrains-mono` yang dipakai di `@theme`.

Hanya bobot yang benar-benar dipakai yang diunduh (5 file woff2, ± 106 KB total) supaya halaman tetap ringan.

---

## 3. Tailwind v4: token tema dan utilitas

Tailwind v4 dikonfigurasi **di CSS**, bukan di `tailwind.config.js`. Di `resources/css/app.css`:

```css
@theme {
    --color-*: initial;          /* buang palet bawaan (red-500, slate-200, ...) */
    --color-primary: #0a5e3d;    /* -> bg-primary, text-primary, border-primary */
    --radius-card: 18px;         /* -> rounded-card */
}

/* Kelas buatan sendiri untuk gradasi, dipakai seperti kelas biasa: class="bg-brand" */
@utility bg-brand {
    background-image: linear-gradient(135deg, #0d7048 0%, #0a5e3d 55%, #0e423e 100%);
}
```

Setiap variabel di `@theme` menjadi kelas utilitas. Karena palet bawaan dihapus, yang bisa dipakai
**hanya** warna tema. Hasilnya konsisten (tidak ada "hijau yang sedikit beda" di halaman lain) dan CSS kecil.

### Mobile-first: `md:` dan `lg:`

Kelas tanpa awalan berlaku di semua ukuran; `md:` mulai 768px; `lg:` mulai 1024px.

```html
<div class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">   <!-- 1 kolom di HP, 2 kolom mulai laptop -->
```

Tata letak aplikasi:

| Lebar                | Navigasi                                                                       |
| -------------------- | ------------------------------------------------------------------------------ |
| < 768px (HP)         | bottom nav (maks. 5 tombol, "Buat Tiket" emas di tengah, sisa tempat = Keluar) |
| 768–1023px (tablet) | tombol hamburger membuka laci (drawer)                                         |
| ≥ 1024px (laptop)   | sidebar putih membulat 256px                                                   |

### Kenapa nama kelas harus ditulis utuh?

Tailwind membuat CSS hanya untuk kelas yang **ditemukan sebagai teks** di file sumber. Kalau kamu menulis
`'bg-' . $warna`, Tailwind tidak tahu kelas `bg-primary` dipakai, jadi CSS-nya tidak dibuat. Karena itu di
`button` dan `badge` kelas disimpan utuh di array:

```php
$variants = ['primary' => 'bg-brand text-on-primary shadow-primary hover:brightness-110', ...];
```

---

## 4. Alpine.js: laci menu dan dropdown

Alpine menambah perilaku kecil langsung di HTML, cocok untuk Blade tanpa framework JS besar.

**Laci (drawer) di tablet**, di `layouts/app.blade.php`:

```blade
<body x-data="{ drawer: false }" @keydown.escape.window="drawer = false">
    <button @click="drawer = true" :aria-expanded="drawer.toString()">...</button>
    <div x-cloak x-show="drawer" @click="drawer = false" class="fixed inset-0 bg-ink/40"></div>
```

- `x-data` = state lokal (di sini satu boolean).
- `@click` = pasang event; `@keydown.escape.window` = tombol Escape di mana pun menutup laci.
- `x-show` = tampil/sembunyi sesuai state.
- `x-cloak` + CSS `[x-cloak] { display: none }` mencegah elemen sempat "berkedip" tampil sebelum Alpine siap.
- `:aria-expanded` (titik dua = binding) memberi tahu pembaca layar apakah menu terbuka.

**Dropdown menu user** memakai `x-data="{ open: false }"` dan `@click.outside="open = false"`
(klik di luar kotak menutupnya). Tombol Keluar tetap form `POST` ke `/logout` dengan `@csrf`, karena logout
mengubah state dan tidak boleh lewat GET.

---

## 5. Menu per peran: `App\Support\Navigation`

Menu tiap peran (PRD 7.5) didefinisikan di **satu** class, dan dipakai oleh sidebar, laci, dan bottom nav.

```php
if (! Route::has($item['route'])) continue;   // halaman belum dibuat
'active' => request()->routeIs(...$match),     // menu aktif disorot
```

`Route::has` membuat menu **bertambah sendiri**: di M1 hanya route `dashboard` yang ada, jadi hanya
"Dashboard" yang tampil. Begitu kamu menambah route `tickets.create` di M2, item "Buat Tiket" langsung muncul
tanpa menyentuh layout. Tes `AppLayoutTest` membuktikan perilaku ini.

Item aktif diberi `aria-current="page"` supaya pembaca layar tahu posisi pengguna.

---

## 6. Halaman error

Laravel otomatis memakai `resources/views/errors/{kode}.blade.php` ketika terjadi error dengan kode itu.
Kita membuat `403` (akses ditolak; memakai pesan dari `abort(403, '...')` kalau ada), `404`, `419` (sesi/CSRF
kedaluwarsa, mis. form dibiarkan terlalu lama), dan `500`. Semuanya memakai layout `x-layouts.error` supaya
seragam dan berbahasa Indonesia, dengan tombol "Kembali ke beranda".

---

## 7. Aksesibilitas

- **Fokus keyboard terlihat**: di `app.css`, `:focus-visible` diberi garis 2px warna `focus`. `focus-visible`
  (bukan `focus`) supaya garis muncul saat navigasi pakai Tab, bukan saat diklik mouse.
- **Skip link** "Langsung ke konten" di awal halaman: pengguna keyboard bisa langsung loncat ke `<main id="konten">`.
- **Target sentuh ≥ 48px** di HP (`min-h-12`), sesuai PRD 6.1.
- **Form**: setiap input punya `<label>`; error dihubungkan dengan `aria-describedby` dan `aria-invalid`.
  Di halaman login, semua kegagalan (password salah, SSO tidak bisa dihubungi, akun nonaktif, terlalu banyak
  percobaan) ditampilkan dalam **satu** alert di atas form (`role="alert"`) supaya tidak dobel.
- **Kontras**: warna teks utama dan sekunder lolos WCAG AA di latar putih (rasio dicatat di komentar `app.css`).
- **Gerak**: `motion-reduce:transition-none` menghormati pengaturan "kurangi animasi" di perangkat.

---

## 8. Ukuran hasil build

Hasil `npm run build` (gzip, perkiraan):

| Berkas               | Ukuran    |
| -------------------- | --------- |
| CSS aplikasi         | ± 5,5 KB |
| JS (Alpine)          | ± 19 KB  |
| Font woff2 (3 bobot) | ± 36 KB  |
| Logo PNG             | ± 4,8 KB |

Kecil karena: palet warna dibatasi, hanya 3 bobot font, ikon SVG ditulis langsung (tidak memuat
seluruh pustaka ikon), dan tidak ada framework JS besar.

---

## 9. Tes tampilan

`tests/Feature/View/` berisi tes yang merender halaman sungguhan dan memeriksa isinya, mis.:

- halaman login menampilkan satu kolom login, tidak menautkan registrasi alumni (link rahasia), dan satu alert saat gagal;
- form registrasi alumni menampilkan daftar fakultas dan tidak mengisi ulang password;
- menu tiap peran benar, dan menu bertambah ketika route baru didaftarkan;
- halaman error berbahasa Indonesia.

Jalankan: `php artisan test --filter=View` atau `php artisan test` untuk semuanya.
