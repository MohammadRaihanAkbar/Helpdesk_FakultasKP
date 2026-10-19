{{--
    Tombol + dialog konfirmasi untuk aksi DESTRUKTIF (hapus, nonaktifkan). PRD US-11: konfirmasi sebelum operasi destructive.

    Pemakaian:
      <x-ui.confirm-form :action="route('admin.services.destroy', $category)" method="DELETE"
                         title="Hapus kategori?" confirm-label="Ya, hapus" trigger-label="Hapus" icon="trash">
          Kategori "Lab Microteaching" akan dihapus permanen.
          <x-slot:fields> <input type="hidden" name="is_active" value="0"> </x-slot:fields>   (opsional)
      </x-ui.confirm-form>

    Cara kerja:
      - Tombol pemicu membuka <dialog> bawaan browser lewat Alpine ($refs.dialog.showModal()).
        <dialog> sudah mengurus fokus keyboard, tombol Esc, dan latar gelap (::backdrop) tanpa library.
      - Form (dengan @csrf + @method) baru terkirim saat tombol konfirmasi di dalam dialog diklik.
      - trigger-variant: gaya tombol pemicu (lihat x-ui.button). "compact" = tombol kecil untuk baris tabel.
--}}
@props([
    'action',
    'method' => 'POST',
    'title',
    'confirmLabel' => 'Ya, lanjutkan',
    'triggerLabel',
    'triggerVariant' => 'danger',
    'icon' => null,
    'compact' => false,
])

@php
    $triggerClasses = [
        'danger' => 'border border-ditolak-dot/40 bg-ditolak-bg text-ditolak-fg hover:brightness-95',
        'secondary' => 'border border-line bg-surface text-ink shadow-sm hover:bg-sunken',
    ][$triggerVariant] ?? '';
    $size = $compact ? 'min-h-10 px-3 text-sm' : 'min-h-12 px-5 text-[15px]';
@endphp

<div x-data class="inline-block">
    <button type="button" @click="$refs.dialog.showModal()"
            {{ $attributes->merge(['class' => "inline-flex items-center justify-center gap-2 rounded-control font-heading font-extrabold {$size} {$triggerClasses}"]) }}>
        @if ($icon) <x-icon :name="$icon" class="size-4" /> @endif
        {{ $triggerLabel }}
    </button>

    <dialog x-ref="dialog" class="m-auto w-[min(92vw,28rem)] rounded-panel border border-line bg-surface p-0 text-left text-ink shadow-pop backdrop:bg-ink/40">
        <form method="POST" action="{{ $action }}" class="p-6">
            @csrf
            @if (strtoupper($method) !== 'POST')
                @method($method)
            @endif
            {{ $fields ?? '' }}

            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-control bg-ditolak-bg text-ditolak-fg">
                    <x-icon name="triangle-alert" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-lg font-extrabold">{{ $title }}</h2>
                    <div class="mt-1 text-sm whitespace-normal text-muted">{{ $slot }}</div>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-ui.button variant="secondary" x-on:click="$refs.dialog.close()">Batal</x-ui.button>
                <x-ui.button type="submit" variant="danger">{{ $confirmLabel }}</x-ui.button>
            </div>
        </form>
    </dialog>
</div>
