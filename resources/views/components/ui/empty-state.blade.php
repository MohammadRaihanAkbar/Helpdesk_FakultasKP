{{--
    Tampilan kosong yang jujur: menjelaskan kenapa kosong dan (jika ada) apa yang bisa dilakukan.
    Tanpa angka contoh atau ilustrasi.

    Pemakaian:
      <x-ui.empty-state icon="inbox" title="Belum ada tiket">
          Tiket yang Anda buat akan muncul di sini.
          <x-slot:action> <x-ui.button href="...">Buat Tiket</x-ui.button> </x-slot:action>
      </x-ui.empty-state>
--}}
@props([
    'icon' => 'inbox',
    'title',
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center rounded-card border border-dashed border-line bg-canvas/60 px-6 py-10 text-center']) }}>
    <span class="grid size-12 place-items-center rounded-control bg-accent-soft text-accent-ink">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <h3 class="mt-3 text-base font-extrabold text-ink">{{ $title }}</h3>
    <div class="mt-1 max-w-md text-sm text-muted">{{ $slot }}</div>

    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
