{{--
    Layout halaman setelah LOGIN (semua peran), mengikuti mockup design/a-dashboard.png.
    Pemakaian: <x-layouts.app title="Dashboard"> ...isi... </x-layouts.app>

    Navigasi responsif (mobile-first):
      < 768px      top bar (logo, avatar) + bottom nav (maks. 5 tombol, "Buat Tiket" emas di tengah)
      768-1023px   top bar + tombol menu yang membuka sidebar sebagai laci (Alpine)
      >= 1024px    sidebar putih membulat selebar 256px, tetap di kiri
    Isi halaman dibatasi maks. 1280px.

    Data menu, kotak pencarian, dan teks kartu hijau datang dari App\Support\Navigation:
    hanya halaman yang route-nya sudah ada yang tampil, jadi tidak ada tombol "mati".
--}}
@props(['title' => null])
@php
    $user = auth()->user();
    $menu = \App\Support\Navigation::for($user);
    $mobileMenu = \App\Support\Navigation::mobile($menu);
    $search = \App\Support\Navigation::search($user);
    $initials = collect(explode(' ', preg_replace('/^(Dr|Prof|Ir|H)\.\s*/i', '', $user->name)))
        ->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    $subtitle = $user->role->label().($user->faculty ? ' · '.$user->faculty->code : ($user->originFaculty ? ' · '.$user->originFaculty->code : ''));
    // Angka kecil di samping menu (kunci "count", mis. jumlah tiket aktif) diisi lewat Navigation.
    // $unread: jumlah notifikasi belum dibaca — dipakai top bar & bottom nav HP.
    $unread = Route::has('notifications.index') ? $user->inAppNotifications()->where('is_read', false)->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A5E3D">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- x-data: state Alpine untuk laci menu tablet + collapse sidebar desktop. --}}
<body x-data="{
    drawer: false,
    collapsed: JSON.parse(localStorage.getItem('sidebar_collapsed') || 'false'),
    toggle() { this.collapsed = !this.collapsed; localStorage.setItem('sidebar_collapsed', JSON.stringify(this.collapsed)); }
}" :data-sidebar="collapsed ? 'collapsed' : 'expanded'" @keydown.escape.window="drawer = false">
    <a href="#konten" class="sr-only z-50 rounded-control bg-surface px-4 py-2 font-semibold focus:not-sr-only focus:fixed focus:top-2 focus:left-2">
        Langsung ke konten
    </a>

    {{-- ===== Sidebar: tetap di laptop (lg), laci di tablet (md), disembunyikan di HP ===== --}}
    <div x-cloak x-show="drawer" x-transition.opacity class="fixed inset-0 z-30 bg-ink/40 lg:hidden" @click="drawer = false" aria-hidden="true"></div>

    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-40 hidden flex-col border-r border-line bg-surface px-3.5 pt-5 pb-4 transition-[width,padding] duration-200 ease-in-out lg:inset-y-4 lg:left-4 lg:flex lg:rounded-panel lg:border lg:bg-surface/85 lg:shadow-card"
           :class="{
               'flex! w-72': drawer
           }"
           aria-label="Menu utama">
        <div class="mb-4 flex items-center gap-3 border-b border-line px-2 pb-5" :class="collapsed && 'lg:justify-center lg:px-0'">
            <img src="{{ asset('images/logo-uinssc.png') }}" alt="" width="32" height="42" class="h-[42px] w-8 shrink-0">
            <div class="leading-tight" :class="collapsed && 'lg:hidden'">
                <p class="font-heading text-[15px] font-extrabold">Helpdesk Fakultas</p>
                <p class="text-xs text-muted">Terpadu · UIN SSC</p>
            </div>
            <button type="button" class="ml-auto inline-flex size-10 items-center justify-center rounded-control text-muted hover:bg-sunken lg:hidden"
                    @click="drawer = false" aria-label="Tutup menu">
                <x-icon name="x" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto">
            <p class="px-3 pb-2 text-[11px] font-extrabold tracking-[.14em] text-muted uppercase" :class="collapsed && 'lg:sr-only'">Menu</p>
            <ul class="space-y-1">
                @foreach ($menu as $item)
                    <li>
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                           :title="collapsed ? '{{ addslashes($item['label']) }}' : null"
                           @class([
                               'relative flex min-h-12 items-center gap-3 rounded-control px-3 text-[15px] transition-colors duration-150 motion-reduce:transition-none',
                               'bg-primary-soft font-bold text-primary before:absolute before:inset-y-2.5 before:left-0 before:w-1 before:rounded-r before:bg-accent' => $item['active'],
                               'font-semibold text-muted hover:bg-sunken hover:text-ink' => ! $item['active'],
                           ])
                           :class="collapsed && 'lg:justify-center lg:px-0'">
                            <x-icon :name="$item['icon']" class="shrink-0" />
                            <span :class="collapsed && 'lg:hidden'">{{ $item['label'] }}</span>
                            @if (! empty($item['count']))
                                <span class="ml-auto rounded-full bg-sunken px-2 py-0.5 font-mono text-xs font-semibold text-ink" :class="collapsed && 'lg:hidden'">{{ $item['count'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- Tombol collapse/expand sidebar (desktop only) --}}
        <button type="button" @click="toggle()" class="mb-2 hidden min-h-10 w-full items-center gap-3 rounded-control px-3 text-sm font-semibold text-muted hover:bg-sunken hover:text-ink lg:flex"
                :class="collapsed && 'lg:justify-center lg:px-0'"
                :title="collapsed ? 'Perluas sidebar' : 'Kecilkan sidebar'">
            <span class="inline-flex shrink-0 transition-transform duration-200" :class="collapsed && 'rotate-180'">
                <x-icon name="chevrons-left" class="size-5" />
            </span>
            <span :class="collapsed && 'lg:hidden'">Kecilkan</span>
        </button>

        {{-- Logout wajib POST + CSRF, bukan tautan GET (mencegah logout paksa dari situs lain). --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex min-h-12 w-full items-center gap-3 rounded-control px-3 text-[15px] font-semibold text-muted hover:bg-sunken hover:text-ink"
                    :class="collapsed && 'lg:justify-center lg:px-0'"
                    :title="collapsed ? 'Keluar' : null">
                <x-icon name="log-out" class="shrink-0" />
                <span :class="collapsed && 'lg:hidden'">Keluar</span>
            </button>
        </form>
    </aside>

    <div class="app-main-layout min-w-0 flex-1 transition-[padding-left] duration-200 ease-in-out">
        {{-- ===== Top bar ===== --}}
        <header class="sticky top-0 z-20 w-full border-b border-line bg-surface/95 backdrop-blur lg:static lg:border-0 lg:bg-transparent lg:backdrop-blur-none">
            <div class="mx-auto flex h-16 w-full max-w-[1280px] items-center gap-2 px-4 md:px-6 lg:h-20 lg:gap-3 lg:pr-7 lg:pl-2">
                <button type="button" class="hidden size-11 items-center justify-center rounded-control text-ink hover:bg-sunken md:inline-flex lg:hidden"
                        @click="drawer = true" :aria-expanded="drawer.toString()" aria-controls="sidebar" aria-label="Buka menu">
                    <x-icon name="menu" class="size-6" />
                </button>

                {{-- Identitas aplikasi di top bar hanya untuk HP & tablet (di laptop sudah ada di sidebar). --}}
                <a href="{{ $menu[0]['url'] ?? url('/') }}" class="flex items-center gap-2 rounded-control lg:hidden">
                    <img src="{{ asset('images/logo-uinssc.png') }}" alt="" width="24" height="32" class="h-8 w-6">
                    <span class="font-heading text-sm font-extrabold leading-tight">Helpdesk</span>
                </a>

                {{-- Breadcrumb: Beranda › judul halaman (laptop saja). --}}
                <nav aria-label="Breadcrumb" class="hidden text-sm text-muted lg:block">
                    <ol class="flex items-center gap-2">
                        <li><a href="{{ $menu[0]['url'] ?? url('/') }}" class="hover:text-ink">Beranda</a></li>
                        @if ($title)
                            <li aria-hidden="true"><x-icon name="chevron-right" class="size-4" /></li>
                            <li class="font-bold text-ink" aria-current="page">{{ $title }}</li>
                        @endif
                    </ol>
                </nav>

                {{-- Sisi kanan top bar: selalu terdorong ke kanan (ml-auto) baik di HP maupun desktop --}}
                <div class="ml-auto flex items-center gap-2 md:gap-3">
                    @if ($search)
                        <form method="GET" action="{{ $search['url'] }}" role="search" class="relative hidden w-72 md:block">
                            <label for="cari" class="sr-only">{{ $search['placeholder'] }}</label>
                            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted" />
                            <input id="cari" name="q" type="search" value="{{ request('q') }}" placeholder="{{ $search['placeholder'] }}"
                                   class="block h-11 w-full rounded-control border border-line bg-surface pr-3 pl-11 text-sm shadow-sm placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent/30 focus:outline-none">
                        </form>
                    @endif

                    @if (Route::has('notifications.index'))
                        <a href="{{ route('notifications.index') }}" class="relative grid size-11 place-items-center rounded-control border border-line bg-surface text-ink shadow-sm hover:bg-sunken">
                            <x-icon name="bell" />
                            <span class="sr-only">Notifikasi{{ $unread ? " ($unread belum dibaca)" : '' }}</span>
                            @if ($unread)
                                <span class="absolute top-1.5 right-1.5 grid h-[18px] min-w-[18px] place-items-center rounded-full border-2 border-surface bg-notif px-1 text-[10px] font-extrabold text-on-primary" aria-hidden="true">{{ $unread > 9 ? '9+' : $unread }}</span>
                            @endif
                        </a>
                    @endif

                    {{-- Menu user: avatar inisial, nama, peran · fakultas, tombol Keluar --}}
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-full p-1 hover:bg-sunken md:border md:border-line md:bg-surface md:pr-4 md:shadow-sm"
                                @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true" aria-controls="menu-user">
                            <span class="inline-flex size-10 items-center justify-center rounded-full bg-brand font-heading text-sm font-extrabold text-on-primary ring-2 ring-accent" aria-hidden="true">{{ $initials }}</span>
                            <span class="hidden text-left leading-tight md:block">
                                <span class="block max-w-44 truncate text-sm font-bold">{{ $user->name }}</span>
                                <span class="block text-xs text-muted">{{ $subtitle }}</span>
                            </span>
                            <span class="sr-only">Menu akun</span>
                        </button>

                        <div id="menu-user" x-cloak x-show="open" x-transition.opacity
                             class="absolute right-0 z-30 mt-2 w-64 rounded-card border border-line bg-surface p-2 shadow-pop">
                            <div class="px-3 py-2">
                                <p class="truncate font-bold">{{ $user->name }}</p>
                                <p class="truncate text-sm text-muted">{{ $user->email }}</p>
                                <p class="mt-2"><x-ui.badge tone="primary">{{ $subtitle }}</x-ui.badge></p>
                            </div>
                            <div class="my-1 border-t border-line"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex min-h-11 w-full items-center gap-3 rounded-control px-3 text-left text-ink hover:bg-sunken">
                                    <x-icon name="log-out" />
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- ===== Isi halaman ===== --}}
        <main id="konten" class="mx-auto w-full max-w-[1280px] px-4 pt-5 pb-28 md:px-6 md:pb-10 lg:pt-0 lg:pr-7 lg:pl-2">
            @if (session('status'))
                <x-ui.alert type="success" class="mb-5">{{ session('status') }}</x-ui.alert>
            @endif

            {{-- Pelanggaran aturan bisnis yang tidak menempel ke field tertentu (App\Exceptions\BusinessRuleException). --}}
            @if ($errors->has(\App\Exceptions\BusinessRuleException::GENERAL_FIELD))
                <x-ui.alert type="error" class="mb-5">{{ $errors->first(\App\Exceptions\BusinessRuleException::GENERAL_FIELD) }}</x-ui.alert>
            @endif

            {{ $slot }}
        </main>
    </div>

    {{-- ===== Bottom nav: hanya di HP (tanpa notifikasi — cukup di header) ===== --}}
    <nav class="fixed inset-x-0 bottom-0 z-20 rounded-t-panel border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] shadow-pop md:hidden" aria-label="Menu utama">
        <ul class="flex items-end">
            @foreach ($mobileMenu as $item)
                <li class="min-w-0 flex-1">
                    @if ($item['fab'] ?? false)
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                           class="flex min-h-16 flex-col items-center justify-end gap-1 pb-2 text-xs font-bold text-ink">
                            <span class="-mt-7 grid size-14 place-items-center rounded-[18px] bg-cta text-on-cta shadow-cta ring-4 ring-surface">
                                <x-icon name="plus" class="size-6" />
                            </span>
                            <span class="max-w-full truncate">{{ $item['label'] }}</span>
                        </a>
                    @else
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                           @class([
                               'flex min-h-16 flex-col items-center justify-center gap-1 px-1 text-xs',
                               'font-bold text-primary' => $item['active'],
                               'font-semibold text-muted' => ! $item['active'],
                           ])>
                            <x-icon :name="$item['icon']" />
                            <span class="max-w-full truncate">{{ $item['short'] ?? $item['label'] }}</span>
                        </a>
                    @endif
                </li>
            @endforeach
            @if (count($menu) < \App\Support\Navigation::MOBILE_LIMIT)
                <li class="min-w-0 flex-1">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex min-h-16 w-full flex-col items-center justify-center gap-1 px-1 text-xs font-semibold text-muted">
                            <x-icon name="log-out" />
                            Keluar
                        </button>
                    </form>
                </li>
            @endif
        </ul>
    </nav>
</body>
</html>
