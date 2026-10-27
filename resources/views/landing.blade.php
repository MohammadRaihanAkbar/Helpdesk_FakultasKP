{{--
    [M6] Halaman depan publik (/) — sebelum login. Data dari LandingController + App\Services\Reports\LandingStatistics.

    Bagian (mobile-first, satu kolom di HP):
      1. Header: logo, tautan bagian, tombol Masuk (tamu) / Buka Dashboard (sudah login)
      2. Hero: foto Gedung Siber UIN SSC + lapisan gelap + kartu kaca berisi 4 angka utama
      3. Profil: apa itu helpdesk, untuk siapa, kenapa, 6 fakultas
      4. Cara kerja: 4 langkah
      5. Layanan: Kelompok Layanan katalog standar
      6. Tren Tiket: angka agregat, grafik 30 hari, kelompok & jenis masalah yang sedang ramai, peringkat SLA fakultas
      7. Footer: UIN SSC + kontak helpdesk

    Privasi: hanya angka agregat; kelompok sensitif "Integritas Dosen" tidak dirinci di Tren Tiket.
    Tautan registrasi alumni (hidden link) SENGAJA tidak ada di halaman ini.
--}}
@php
    use App\Support\Format;

    $summary = $stats['summary'];
    $hasData = $summary['total'] > 0;
    $audienceLabels = ['mahasiswa' => 'Mahasiswa', 'dosen' => 'Dosen', 'alumni' => 'Alumni'];
    $groupIcons = [
        'Sistem Akademik & Perkuliahan' => 'graduation-cap', 'Pelayanan Administrasi & Birokrasi' => 'file', 'Keuangan & UKT' => 'chart',
        'Integritas Dosen' => 'shield-check', 'Fasilitas, Sarana & Prasarana' => 'landmark', 'Akun, Jaringan & IT' => 'key',
        'Jadwal Mengajar' => 'calendar', 'Transkrip Nilai' => 'paperclip', 'Kesalahan Data PDDIKTI' => 'search',
    ];
    $cta = $dashboardUrl
        ? ['url' => $dashboardUrl, 'label' => 'Buka Dashboard', 'icon' => 'layout-dashboard']
        : ['url' => route('login'), 'label' => 'Masuk', 'icon' => 'log-out'];
    $nav = ['profil' => 'Profil', 'cara-kerja' => 'Cara Kerja', 'layanan' => 'Layanan', 'tren' => 'Tren Tiket'];
@endphp

<x-layouts.public title="Beranda" description="Helpdesk Fakultas Terpadu UIN Siber Syekh Nurjati Cirebon: satu pintu layanan aduan untuk mahasiswa, dosen, dan alumni di enam fakultas.">
    <x-slot:head>
        {{-- Foto hero = elemen terbesar di layar pertama: dimuat lebih dulu (preload), versi kecil untuk HP. --}}
        <link rel="preload" as="image" type="image/webp" href="{{ asset('images/gedung-siber.webp') }}"
              imagesrcset="{{ asset('images/gedung-siber-960.webp') }} 960w, {{ asset('images/gedung-siber.webp') }} 1600w" imagesizes="100vw">
    </x-slot:head>

    {{-- ================= 1–2. Header + Hero ================= --}}
    <section class="relative isolate overflow-hidden bg-night text-on-primary" aria-labelledby="judul">
        <picture>
            <source type="image/webp" srcset="{{ asset('images/gedung-siber-960.webp') }} 960w, {{ asset('images/gedung-siber.webp') }} 1600w" sizes="100vw">
            <img src="{{ asset('images/gedung-siber.jpg') }}" alt="Gedung Siber UIN Siber Syekh Nurjati Cirebon" width="1600" height="900"
                 fetchpriority="high" decoding="async" class="absolute inset-0 -z-20 size-full object-cover object-[60%_40%]">
        </picture>
        <div class="bg-hero-overlay absolute inset-0 -z-10" aria-hidden="true"></div>

        <header class="mx-auto flex w-full max-w-[1280px] items-center gap-3 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 rounded-control">
                <img src="{{ asset('images/logo-uinssc.png') }}" alt="" width="30" height="40" class="h-10 w-[30px]">
                <span class="leading-tight">
                    <span class="block font-heading text-[15px] font-extrabold">Helpdesk Fakultas</span>
                    <span class="block text-xs text-on-primary/80">Terpadu · UIN SSC</span>
                </span>
            </a>
            <nav aria-label="Bagian halaman" class="ml-auto hidden md:block">
                <ul class="flex items-center gap-1 text-sm font-semibold">
                    @foreach ($nav as $id => $label)
                        <li><a href="#{{ $id }}" class="rounded-control px-3 py-2 text-on-primary/85 hover:bg-on-primary/10 hover:text-on-primary">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <a href="{{ $cta['url'] }}" class="ml-auto inline-flex min-h-11 items-center gap-2 rounded-control bg-cta px-4 font-heading text-sm font-extrabold text-on-cta shadow-cta hover:brightness-105 md:ml-2">
                <x-icon :name="$cta['icon']" class="size-4" /> {{ $cta['label'] }}
            </a>
        </header>

        <div class="mx-auto w-full max-w-[1280px] px-4 pt-10 pb-10 sm:px-6 md:pt-20 lg:px-8 lg:pt-28 lg:pb-14">
            <p class="flex items-center gap-3 text-sm font-semibold text-on-primary/85">
                <span class="h-0.5 w-10 rounded-full bg-accent" aria-hidden="true"></span>
                UIN Siber Syekh Nurjati Cirebon
            </p>
            <h1 id="judul" class="mt-4 max-w-3xl text-4xl leading-[1.05] font-extrabold tracking-[-0.03em] sm:text-5xl lg:text-6xl">
                Helpdesk Fakultas UINSSC
                <span class="mt-2 block text-2xl font-bold tracking-normal text-cream sm:text-3xl">Satu pintu untuk setiap aduan fakultas.</span>
            </h1>
            <p class="mt-5 max-w-2xl text-base leading-relaxed text-on-primary/90 sm:text-lg">
                Laporkan kendala akademik, administrasi, keuangan, fasilitas, hingga jaringan kampus
            </p>
            <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                <a href="{{ $cta['url'] }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-control bg-cta px-6 font-heading font-extrabold text-on-cta shadow-cta hover:brightness-105">
                    <x-icon :name="$cta['icon']" class="size-5" /> {{ $dashboardUrl ? 'Buka Dashboard' : 'Masuk & Buat Tiket' }}
                </a>
                <a href="#tren" class="glass inline-flex min-h-12 items-center justify-center gap-2 rounded-control px-6 font-heading font-extrabold text-on-primary hover:bg-on-primary/20">
                    <x-icon name="chart" class="size-5" /> Lihat Tren Tiket
                </a>
            </div>
        </div>
    </section>

    <main id="konten">
        {{-- ================= 3. Profil ================= --}}
        <section id="profil" class="mx-auto w-full max-w-[1280px] scroll-mt-4 px-4 py-14 sm:px-6 md:py-20 lg:px-8" aria-labelledby="judul-profil">
            <div class="grid gap-10 lg:grid-cols-[1.1fr_1fr] lg:gap-16">
                <div>
                    <p class="flex items-center gap-2 font-heading text-xs font-extrabold tracking-[.18em] text-accent-ink uppercase">
                        <x-icon name="star" class="size-3.5 fill-current text-accent" /> Profil Helpdesk
                    </p>
                    <h2 id="judul-profil" class="mt-3 text-3xl font-extrabold sm:text-4xl">Layanan aduan terpadu untuk enam fakultas</h2>
                    <div class="mt-5 space-y-4 text-[15px] leading-relaxed text-muted sm:text-base">
                        <p>
                            <b class="text-ink">Helpdesk Fakultas Terpadu</b> adalah sistem tiket aduan UIN Siber Syekh Nurjati Cirebon.
                            Mahasiswa, dosen, dan alumni cukup membuat satu tiket untuk kendala di fakultas, lalu memantau prosesnya
                            sampai selesai tanpa harus datang ke banyak bagian atau mengulang cerita yang sama.
                        </p>
                        <p>
                            Setiap tiket masuk ke antrean Staff fakultas sesuai kelompok layanannya, punya target batas waktu penyelesaian,
                            dan riwayat yang tercatat: kapan dibuat, diambil Staff, dibalas, hingga selesai. Setelah selesai, pelapor
                            memberi penilaian supaya layanan terus membaik.
                        </p>
                    </div>

                    <ul class="mt-6 flex flex-wrap gap-2" aria-label="Fakultas yang terhubung">
                        @foreach ($stats['faculties'] as $faculty)
                            <li class="rounded-full border border-line bg-surface/80 px-3.5 py-1.5 text-sm" title="{{ $faculty['name'] }}">
                                <span class="font-mono font-semibold text-ink">{{ $faculty['code'] }}</span>
                                <span class="hidden text-muted sm:inline">· {{ $faculty['name'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="grid content-start gap-3">
                    <h3 class="font-heading text-sm font-extrabold tracking-wide text-muted uppercase">Untuk siapa?</h3>
                    @foreach ([
                        ['graduation-cap', 'Mahasiswa', 'Akademik & perkuliahan, administrasi, UKT, fasilitas, akun & jaringan. Masuk dengan NIM dan password SSO kampus.'],
                        ['user', 'Dosen', 'Administrasi, fasilitas, jadwal mengajar, akun & jaringan. Masuk dengan NIP dan password SSO kampus.'],
                        ['link', 'Alumni', 'Transkrip nilai dan perbaikan data PDDIKTI. Alumni lulus 2022 ke atas masuk dengan NIM lewat SSO.'],
                    ] as [$icon, $title, $text])
                        <div class="flex gap-3 rounded-card border border-line bg-surface/85 p-4 shadow-card">
                            <span class="grid size-10 shrink-0 place-items-center rounded-control bg-primary-soft text-primary"><x-icon :name="$icon" /></span>
                            <div>
                                <p class="font-bold text-ink">{{ $title }}</p>
                                <p class="mt-0.5 text-sm text-muted">{{ $text }}</p>
                            </div>
                        </div>
                    @endforeach

                    <h3 class="mt-4 font-heading text-sm font-extrabold tracking-wide text-muted uppercase">Kenapa lewat helpdesk?</h3>
                    <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                        @foreach ([
                            ['inbox', 'Satu pintu', 'Semua aduan fakultas di satu tempat, langsung ke Staff yang tepat.'],
                            ['clock', 'Target waktu jelas', 'Setiap jenis masalah punya target batas waktu penanganan.'],
                            ['eye', 'Transparan', 'Status & riwayat tiket bisa dipantau kapan saja.'],
                        ] as [$icon, $title, $text])
                            <div class="rounded-card border border-line bg-surface/85 p-4 shadow-card">
                                <span class="grid size-9 place-items-center rounded-control bg-accent-soft text-accent-ink"><x-icon :name="$icon" class="size-4" /></span>
                                <p class="mt-2 font-bold text-ink">{{ $title }}</p>
                                <p class="mt-0.5 text-sm text-muted">{{ $text }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ================= 4. Cara kerja ================= --}}
        <section id="cara-kerja" class="scroll-mt-4 border-y border-line bg-surface/60 py-14 md:py-20" aria-labelledby="judul-cara">
            <div class="mx-auto w-full max-w-[1280px] px-4 sm:px-6 lg:px-8">
                <h2 id="judul-cara" class="text-3xl font-extrabold sm:text-4xl">Cara kerja</h2>
                <p class="mt-2 max-w-2xl text-muted">Empat langkah dari aduan sampai selesai.</p>
                <ol class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['Masuk', 'Login dengan akun SSO kampus (NIM / NIP). Staff & admin memakai email.', 'log-out'],
                        ['Buat tiket', 'Pilih fakultas tujuan & jenis masalah, ceritakan kendalanya, lampirkan bukti bila perlu.', 'circle-plus'],
                        ['Diproses Staff', 'Staff fakultas mengambil tiket, membalas lewat thread, dan bekerja sesuai target batas waktu.', 'refresh'],
                        ['Selesai & beri rating', 'Tiket ditutup dengan bukti penyelesaian. Beri penilaian 1 sampai 5 bintang.', 'star'],
                    ] as $i => [$title, $text, $icon])
                        <li class="relative rounded-card border border-line bg-surface p-5 shadow-card">
                            <span class="font-mono text-xs font-semibold text-accent-ink">Langkah {{ $i + 1 }}</span>
                            <span class="mt-3 grid size-11 place-items-center rounded-control bg-brand text-on-primary"><x-icon :name="$icon" /></span>
                            <p class="mt-3 font-heading text-lg font-extrabold text-ink">{{ $title }}</p>
                            <p class="mt-1 text-sm text-muted">{{ $text }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- ================= 5. Layanan ================= --}}
        <section id="layanan" class="mx-auto w-full max-w-[1280px] scroll-mt-4 px-4 py-14 sm:px-6 md:py-20 lg:px-8" aria-labelledby="judul-layanan">
            <h2 id="judul-layanan" class="text-3xl font-extrabold sm:text-4xl">Kelompok layanan</h2>
            <p class="mt-2 max-w-2xl text-muted">Katalog layanan standar yang tersedia di setiap fakultas. Fakultas dapat menambah layanan khusus.</p>
            <ul class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($catalog as $group)
                    <li class="flex gap-3 rounded-card border border-line bg-surface/85 p-4 shadow-card">
                        <span class="grid size-10 shrink-0 place-items-center rounded-control bg-primary-soft text-primary"><x-icon :name="$groupIcons[$group['name']] ?? 'ticket'" /></span>
                        <div class="min-w-0">
                            <p class="font-bold text-ink">{{ $group['name'] }}</p>
                            <p class="mt-0.5 text-sm text-muted">{{ $group['description'] }}</p>
                            <p class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($group['audiences'] as $audience)
                                    <x-ui.badge tone="neutral">{{ $audienceLabels[$audience] ?? $audience }}</x-ui.badge>
                                @endforeach
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- ================= 6. Tren Tiket ================= --}}
        <section id="tren" class="scroll-mt-4 border-t border-line bg-surface/60 py-14 md:py-20" aria-labelledby="judul-tren">
            <div class="mx-auto w-full max-w-[1280px] px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h2 id="judul-tren" class="text-3xl font-extrabold sm:text-4xl">Tren tiket</h2>
                        <p class="mt-2 max-w-2xl text-muted">Gambaran layanan seluruh fakultas: angka gabungan, tanpa data pribadi.</p>
                    </div>
                    <p class="text-xs text-muted">Diperbarui berkala · {{ \Illuminate\Support\Carbon::parse($stats['generated_at'])->translatedFormat('j M Y, H:i') }}</p>
                </div>


                <div class="mt-4 grid gap-4 lg:grid-cols-[1.5fr_1fr]">
                    <x-ui.card title="Volume tiket 30 hari terakhir" description="Jumlah tiket masuk per hari, semua fakultas." class="min-w-0">
                        @if ($summary['last_30_days'] > 0)
                            <x-charts.bars caption="Jumlah tiket masuk per hari, 30 hari terakhir"
                                           :labels="array_column($stats['volume'], 'label')"
                                           :series="[['name' => 'Tiket masuk', 'values' => array_column($stats['volume'], 'value'), 'class' => 'bg-primary']]" />
                        @else
                            <x-ui.empty-state icon="chart" title="Belum ada tiket bulan ini">Grafik muncul setelah ada tiket yang masuk.</x-ui.empty-state>
                        @endif
                    </x-ui.card>

                    <x-ui.card title="Sedang ramai" description="Kelompok layanan terbanyak 30 hari terakhir, dibanding 30 hari sebelumnya." class="min-w-0">
                        @if (empty($stats['trending']))
                            <x-ui.empty-state icon="chart" title="Belum ada data tren">Tren muncul setelah ada tiket dalam 30 hari terakhir.</x-ui.empty-state>
                        @else
                            <ol class="space-y-3.5">
                                @foreach ($stats['trending'] as $i => $row)
                                    <li>
                                        <div class="flex items-center justify-between gap-3 text-sm">
                                            <span class="flex min-w-0 items-center gap-2">
                                                <span class="w-4 shrink-0 text-right font-mono text-xs text-muted">{{ $i + 1 }}</span>
                                                <span class="truncate font-semibold text-ink">{{ $row['name'] }}</span>
                                            </span>
                                            <span class="flex shrink-0 items-center gap-2">
                                                <span class="font-mono text-xs font-semibold text-ink">{{ $row['count'] }}</span>
                                                @if ($row['change'] === null)
                                                    <x-ui.badge tone="info">baru</x-ui.badge>
                                                @elseif ($row['change'] > 0)
                                                    <x-ui.badge tone="warning">▲ {{ $row['change'] }}%</x-ui.badge>
                                                @elseif ($row['change'] < 0)
                                                    <x-ui.badge tone="success">▼ {{ abs($row['change']) }}%</x-ui.badge>
                                                @else
                                                    <x-ui.badge tone="neutral">tetap</x-ui.badge>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="mt-1.5 ml-6 h-2 overflow-hidden rounded-full bg-sunken" aria-hidden="true">
                                            <div class="h-full rounded-full bg-cyan" style="width: {{ $row['share'] }}%"></div>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                            <p class="mt-4 text-xs text-muted">▲ naik / ▼ turun dibanding 30 hari sebelumnya. Kategori sensitif tidak ditampilkan rinciannya.</p>
                        @endif
                    </x-ui.card>
                </div>

                <div class="mt-4">
                    <x-ui.card title="Jenis masalah terbanyak" description="30 hari terakhir, semua fakultas." class="min-w-0">
                        @if (empty($stats['issues']))
                            <p class="text-sm text-muted">Belum ada data.</p>
                        @else
                            <ul class="divide-y divide-line text-sm">
                                @foreach ($stats['issues'] as $issue)
                                    <li class="flex items-center justify-between gap-3 py-2.5">
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold text-ink">{{ $issue['name'] }}</span>
                                            <span class="block truncate text-xs text-muted">{{ $issue['group'] }}</span>
                                        </span>
                                        <span class="font-mono text-sm font-semibold text-ink">{{ $issue['count'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-ui.card>
                </div>
            </div>
        </section>

        {{-- Ajakan terakhir --}}
        <section class="mx-auto w-full max-w-[1280px] px-4 py-14 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-panel bg-brand px-6 py-8 text-on-primary shadow-pop md:flex md:items-center md:justify-between md:px-10">
                <div>
                    <h2 class="text-2xl font-extrabold">Ada kendala di fakultas?</h2>
                    <p class="mt-1 text-on-primary/90">Buat tiket sekarang dan pantau penyelesaiannya.</p>
                </div>
                <a href="{{ $cta['url'] }}" class="mt-5 inline-flex min-h-12 items-center justify-center gap-2 rounded-control bg-cta px-6 font-heading font-extrabold text-on-cta shadow-cta hover:brightness-105 md:mt-0">
                    <x-icon :name="$cta['icon']" class="size-5" /> {{ $cta['label'] }}
                </a>
            </div>
        </section>
    </main>

    {{-- ================= 7. Footer ================= --}}
    <footer class="border-t border-line bg-night text-on-primary/85">
        <div class="mx-auto grid w-full max-w-[1280px] gap-6 px-4 py-10 text-sm sm:px-6 md:grid-cols-[1.4fr_1fr_1fr] lg:px-8">
            <div class="flex gap-3">
                <img src="{{ asset('images/logo-uinssc.png') }}" alt="Logo UIN SSC" width="30" height="40" loading="lazy" class="h-10 w-[30px]">
                <div>
                    <p class="font-heading font-extrabold text-on-primary">Helpdesk Fakultas Terpadu</p>
                    <p>UIN Siber Syekh Nurjati Cirebon</p>
                </div>
            </div>
            <div>
                <p class="font-bold text-on-primary">Kontak helpdesk</p>
                <a href="mailto:{{ $contactEmail }}" class="mt-1 inline-flex items-center gap-1.5 break-all underline-offset-4 hover:underline">{{ $contactEmail }}</a>
            </div>
            <div>
                <p class="font-bold text-on-primary">Akses</p>
                <a href="{{ $cta['url'] }}" class="mt-1 inline-block underline-offset-4 hover:underline">{{ $cta['label'] }}</a>
            </div>
        </div>
        <p class="border-t border-on-primary/10 px-4 py-4 text-center text-xs text-on-primary/60">&copy; {{ now()->year }} UIN Siber Syekh Nurjati Cirebon · Helpdesk Fakultas Terpadu</p>
    </footer>
</x-layouts.public>
