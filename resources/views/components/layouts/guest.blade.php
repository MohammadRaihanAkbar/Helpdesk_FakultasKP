{{--
    Layout halaman TAMU: login, registrasi alumni, dan halaman error.
    Pemakaian: <x-layouts.guest title="Masuk"> ...isi kartu... </x-layouts.guest>

    Dua bentuk (mockup design/a-login.png):
      - tanpa slot "aside": satu kartu di tengah dengan logo di atasnya (registrasi, error);
      - dengan <x-slot:aside>: layar terbagi dua di laptop, cerita/statistik di kiri dan kartu
        form di kanan. Di HP kartu form tampil PERTAMA (yang paling dibutuhkan), sisi kiri di bawahnya.

    Props:
      title -> judul tab browser
      width -> lebar kartu tanpa aside: 'sm' (error) atau 'lg' (registrasi)
--}}
@props([
    'title' => null,
    'width' => 'sm',
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A5E3D">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    {{-- @fonts: preload + @font-face (disajikan dari public/build). @vite: CSS & JS hasil build. --}}
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @isset($aside)
        <div class="mx-auto grid min-h-dvh w-full max-w-[1440px] gap-8 px-4 py-6 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,480px)] lg:items-center lg:gap-16 lg:px-16 lg:py-10">
            <main class="order-1 w-full rounded-panel border border-line bg-surface/90 p-6 shadow-card sm:p-10 lg:order-2">
                @if (session('status'))
                    <x-ui.alert type="success" class="mb-5">{{ session('status') }}</x-ui.alert>
                @endif

                {{ $slot }}
            </main>

            <aside class="order-2 lg:order-1">
                {{ $aside }}
            </aside>
        </div>
    @else
        <div class="mx-auto flex min-h-dvh w-full flex-col items-center px-4 py-8 sm:py-12 {{ $width === 'lg' ? 'max-w-2xl' : 'max-w-md' }}">
            <header class="mb-6 flex flex-col items-center text-center">
                <a href="{{ url('/') }}" class="rounded-control">
                    <img src="{{ asset('images/logo-uinssc.png') }}" alt="Logo UIN SSC" width="54" height="72" class="h-[72px] w-[54px]">
                </a>
                <p class="mt-3 font-heading text-xl font-extrabold text-ink">Helpdesk Fakultas Terpadu</p>
                <p class="text-sm text-muted">UIN Siber Syekh Nurjati Cirebon</p>
            </header>

            <main class="w-full rounded-panel border border-line bg-surface/90 p-6 shadow-card sm:p-8">
                @if (session('status'))
                    <x-ui.alert type="success" class="mb-5">{{ session('status') }}</x-ui.alert>
                @endif

                {{ $slot }}
            </main>

            <footer class="mt-auto pt-8 text-center text-sm text-muted">
                &copy; {{ now()->year }} UIN Siber Syekh Nurjati Cirebon · Helpdesk Fakultas Terpadu
            </footer>
        </div>
    @endisset
</body>
</html>
