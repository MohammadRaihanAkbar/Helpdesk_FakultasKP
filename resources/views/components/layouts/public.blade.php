{{--
    [M6] Layout halaman PUBLIK selebar layar (halaman depan /). Tidak ada sidebar/menu peran.
    Pemakaian: <x-layouts.public title="..." description="..."> ...isi... </x-layouts.public>

    Beda dengan x-layouts.guest (kartu login di tengah): di sini isi halaman yang mengatur lebarnya sendiri.
    Gambar & aset dari server sendiri saja, jadi tetap lolos Content-Security-Policy M5 tanpa pengecualian.
--}}
@props(['title' => null, 'description' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B0F19">
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    {{ $head ?? '' }}
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#konten" class="sr-only z-50 rounded-control bg-surface px-4 py-2 font-semibold focus:not-sr-only focus:fixed focus:top-2 focus:left-2">
        Langsung ke konten
    </a>
    {{ $slot }}
</body>
</html>
