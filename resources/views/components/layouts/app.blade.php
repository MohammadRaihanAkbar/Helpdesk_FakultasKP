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
    [$tipTitle, $tipText] = \App\Support\Navigation::tip($user->role);
    $initials = collect(explode(' ', preg_replace('/^(Dr|Prof|Ir|H)\.\s*/i', '', $user->name)))
        ->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    $subtitle = $user->role->label().($user->faculty ? ' · '.$user->faculty->code : ($user->originFaculty ? ' · '.$user->originFaculty->code : ''));
    // Angka kecil di samping menu (kunci "count", mis. jumlah tiket aktif) diisi lewat Navigation.
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
{{-- x-data: state Alpine untuk laci menu tablet. Escape menutup laci. --}}
<body x-data="{ drawer: false }" @keydown.escape.window="drawer = false">
    <a href="#konten" class="sr-only z-50 rounded-control bg-surface px-4 py-2 font-semibold focus:not-sr-only focus:fixed focus:top-2 focus:left-2">
        Langsung ke konten
    </a>

    {{-- ===== Sidebar: tetap di laptop (lg), laci di tablet (md), disembunyikan di HP ===== --}}
    <div x-cloak x-show="drawer" x-transition.opacity class="fixed inset-0 z-30 bg-ink/40 lg:hidden" @click="drawer = false" aria-hidden="true"></div>

    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col border-r border-line bg-surface px-3.5 pt-5 pb-4 lg:inset-y-4 lg:left-4 lg:flex lg:w-64 lg:rounded-panel lg:border lg:bg-surface/85 lg:shadow-card"
           :class="drawer ? 'flex!' : ''"
           aria-label="Menu utama">
        <div class="mb-4 flex items-center gap-3 border-b border-line px-2 pb-5">
            <img src="{{ asset('images/logo-uinssc.png') }}" alt="" width="32" height="42" class="h-[42px] w-8">
            <div class="leading-tight">
                <p class="font-heading text-[15px] font-extrabold">Helpdesk Fakultas</p>
                <p class="text-xs text-muted">Terpadu · UIN SSC</p>
            </div>
            <button type="button" class="ml-auto inline-flex size-10 items-center justify-center rounded-control text-muted hover:bg-sunken lg:hidden"
                    @click="drawer = false" aria-label="Tutup menu">
                <x-icon name="x" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto">
            <p class="px-3 pb-2 text-[11px] font-extrabold tracking-[.14em] text-muted uppercase">Menu</p>
            <ul class="space-y-1">
                @foreach ($menu as $item)
                    <li>
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                           @class([
                               'relative flex min-h-12 items-center gap-3 rounded-control px-3 text-[15px] transition-colors duration-150 motion-reduce:transition-none',
                               'bg-primary-soft font-bold text-primary before:absolute before:top-3 before:bottom-3 before:-left-3.5 before:w-1 before:rounded-r before:bg-accent' => $item['active'],
                               'font-semibold text-muted hover:bg-sunken hover:text-ink' => ! $item['active'],
                           ])>
                            <x-icon :name="$item['icon']" />
                            {{ $item['label'] }}
                            @if (! empty($item['count']))
                                <span class="ml-auto rounded-full bg-sunken px-2 py-0.5 font-mono text-xs font-semibold text-ink">{{ $item['count'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- Kartu hijau (mockup A): kalimat bantuan singkat sesuai peran, tanpa angka contoh. --}}
        <div class="relative mb-3 overflow-hidden rounded-card bg-brand p-4 text-on-primary">
            <x-icon name="star" class="absolute top-2 right-3 size-4 fill-current text-accent opacity-70" />
            <x-icon name="star" class="absolute top-6 right-8 size-3 fill-current text-accent opacity-50" />
            <p class="font-heading text-sm font-extrabold">{{ $tipTitle }}</p>
            <p class="mt-1 text-[13px] leading-snug text-on-primary/90">{{ $tipText }}</p>
        </div>

        {{-- Logout wajib POST + CSRF, bukan tautan GET (mencegah logout paksa dari situs lain). --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex min-h-12 w-full items-center gap-3 rounded-control px-3 text-[15px] font-semibold text-muted hover:bg-sunken hover:text-ink">
                <x-icon name="log-out" />
                Keluar
            </button>
        </form>
    </aside>

    <div class="lg:pl-[18rem]">
        {{-- ===== Top bar ===== --}}
        <header class="sticky top-0 z-20 border-b border-line bg-surface/95 lg:static lg:border-0 lg:bg-transparent">
            <div class="mx-auto flex h-16 w-full max-w-[1280px] items-center gap-2 px-4 md:px-6 lg:h-20 lg:gap-3 lg:pr-7 lg:pl-2">
                <button type="button" class="hidden size-11 items-center justify-center rounded-control text-ink hover:bg-sunken md:inline-flex lg:hidden"
                        @click="drawer = true" :aria-expanded="drawer.toString()" aria-controls="sidebar" aria-label="Buka menu">
                    <x-icon name="menu" class="size-6" />
                </button>

                {{-- Identitas aplikasi di top bar hanya untuk HP & tablet (di laptop sudah ada di sidebar). --}}
                <a href="{{ $menu[0]['url'] ?? url('/') }}" class="flex items-center gap-2.5 rounded-control lg:hidden">
                    <img src="{{ asset('images/logo-uinssc.png') }}" alt="" width="27" height="36" class="h-9 w-[27px]">
                    <span class="leading-tight">
                        <span class="block font-heading text-[15px] font-extrabold">Helpdesk</span>
                        <span class="block text-xs text-muted">Fakultas Terpadu</span>
                    </span>
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

                @if ($search)
                    <form method="GET" action="{{ $search['url'] }}" role="search" class="relative ml-auto hidden w-72 md:block">
                        <label for="cari" class="sr-only">{{ $search['placeholder'] }}</label>
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted" />
                        <input id="cari" name="q" type="search" value="{{ request('q') }}" placeholder="{{ $search['placeholder'] }}"
                               class="block h-11 w-full rounded-control border border-line bg-surface pr-3 pl-11 text-sm shadow-sm placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent/30 focus:outline-none">
                    </form>
                @endif

                @if (Route::has('notifications.index'))
                    @php($unread = $user->inAppNotifications()->where('is_read', false)->count())
                    <a href="{{ route('notifications.index') }}" @class(['relative grid size-11 place-items-center rounded-control border border-line bg-surface text-ink shadow-sm hover:bg-sunken', 'ml-auto' => ! $search])>
                        <x-icon name="bell" />
                        <span class="sr-only">Notifikasi{{ $unread ? " ($unread belum dibaca)" : '' }}</span>
                        @if ($unread)
                            <span class="absolute top-1.5 right-1.5 grid h-[18px] min-w-[18px] place-items-center rounded-full border-2 border-surface bg-notif px-1 text-[10px] font-extrabold text-on-primary" aria-hidden="true">{{ $unread > 9 ? '9+' : $unread }}</span>
                        @endif
                    </a>
                @endif

                {{-- Menu user: avatar inisial, nama, peran · fakultas, tombol Keluar --}}
                <div @class(['relative', 'ml-auto' => ! $search && ! Route::has('notifications.index')]) x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
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

    {{-- ===== Bottom nav: hanya di HP ===== --}}
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
                            {{-- 'short' = label pendek khusus bottom nav HP (mis. "Antrean"), supaya tidak terpotong. --}}
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
