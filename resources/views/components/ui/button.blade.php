{{--
    Tombol. Menjadi <a> jika diberi href, selain itu <button>.

    Pemakaian:
      <x-ui.button type="submit" icon="shield-check">Masuk</x-ui.button>
      <x-ui.button variant="secondary" href="{{ route('login') }}" icon="arrow-left">Kembali</x-ui.button>
      <x-ui.button variant="cta" icon="plus">Buat Tiket Baru</x-ui.button>

    Varian (mockup A):
      primary   hijau bergradasi + bayangan hijau (aksi utama)
      cta       emas bergradasi, teks gelap (ajakan "Buat Tiket Baru")
      secondary putih bergaris
      ghost     teks hijau tanpa latar
      danger    merah lembut (aksi menolak/menghapus)
    Tinggi minimal 48px (target sentuh PRD 6.1).
--}}
@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
    'icon' => null,
])

@php
    // Nama kelas ditulis utuh (bukan disusun dari potongan string) supaya ditemukan Tailwind saat build.
    $variants = [
        'primary' => 'bg-brand text-on-primary shadow-primary hover:brightness-110',
        'cta' => 'bg-cta text-on-cta shadow-cta hover:brightness-105',
        'secondary' => 'border border-line bg-surface text-ink shadow-sm hover:bg-sunken',
        'ghost' => 'text-primary hover:bg-primary-soft',
        'danger' => 'border border-ditolak-dot/40 bg-ditolak-bg text-ditolak-fg hover:brightness-95',
    ];

    $classes = 'inline-flex min-h-12 items-center justify-center gap-2.5 rounded-control px-5 font-heading text-[15px] font-extrabold '
        .'transition duration-150 motion-reduce:transition-none disabled:cursor-not-allowed disabled:opacity-60 '
        .($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-icon :name="$icon" /> @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-icon :name="$icon" /> @endif
        {{ $slot }}
    </button>
@endif
