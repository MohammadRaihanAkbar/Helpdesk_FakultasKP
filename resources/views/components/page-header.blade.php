{{--
    Judul halaman + keterangan + tombol aksi di kanan (dipakai halaman daftar & form).

    Pemakaian:
      <x-page-header title="Pengaturan Layanan" description="Katalog kategori FITK">
          <x-slot:action> <x-ui.button href="...">Tambah</x-ui.button> </x-slot:action>
      </x-page-header>
--}}
@props(['title', 'description' => null, 'back' => null])

<div {{ $attributes->merge(['class' => 'mb-5 flex flex-col gap-4 md:flex-row md:items-end md:justify-between']) }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-2 inline-flex min-h-10 items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                <x-icon name="arrow-left" class="size-4" /> Kembali
            </a>
        @endif
        <h1 class="text-2xl font-extrabold text-ink md:text-[28px]">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-[15px] text-muted">{{ $description }}</p>
        @endif
    </div>
    @isset($action)
        <div class="flex shrink-0 flex-wrap gap-2">{{ $action }}</div>
    @endisset
</div>
