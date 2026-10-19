{{--
    Kartu/panel: putih, garis tepi tipis, sudut 18px, bayangan halus (mockup A).

    Pemakaian:
      <x-ui.card title="Profil" description="Data dari SSO kampus"> ...isi... </x-ui.card>
      <x-ui.card title="Tiket Terbaru"> <x-slot:action><a href="...">Lihat semua</a></x-slot:action> ... </x-ui.card>
      <x-ui.card> ...isi tanpa judul... </x-ui.card>
--}}
@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->merge(['class' => 'rounded-card border border-line bg-surface/85 p-4 shadow-card md:p-6']) }}>
    @if ($title)
        <header class="mb-4 flex items-end justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-[17px] font-extrabold text-ink">{{ $title }}</h2>
                @if ($description)
                    <p class="mt-0.5 text-sm text-muted">{{ $description }}</p>
                @endif
            </div>
            @isset($action)
                <div class="shrink-0">{{ $action }}</div>
            @endisset
        </header>
    @endif

    {{ $slot }}
</section>
