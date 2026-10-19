{{--
    Isi halaman error (403/404/419/429/500), memakai layout tamu supaya gayanya sama.
    Pemakaian (di resources/views/errors/404.blade.php):
      <x-layouts.error code="404" title="Halaman tidak ditemukan">Penjelasan singkat.</x-layouts.error>

    Sengaja tidak memanggil database/session apa pun: halaman 500 harus tetap bisa tampil
    walau database sedang bermasalah.
--}}
@props(['code', 'title'])

<x-layouts.guest :title="$title">
    <p class="font-mono text-sm font-semibold text-accent-ink">Error {{ $code }}</p>
    <h1 class="mt-1 text-2xl font-extrabold">{{ $title }}</h1>
    <div class="mt-2 text-base text-muted">{{ $slot }}</div>

    <div class="mt-6">
        <x-ui.button href="{{ url('/') }}" icon="arrow-left" class="w-full sm:w-auto">Kembali ke beranda</x-ui.button>
    </div>
</x-layouts.guest>
