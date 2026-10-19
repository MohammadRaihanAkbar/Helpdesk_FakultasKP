{{--
    Satu baris kategori di Pengaturan Layanan (dipakai untuk Kelompok Layanan maupun Jenis Masalah).
    Variabel: $category, $isChild (bool), $parentActive (bool, untuk anak), $canHaveChildren (bool, untuk kelompok)
--}}
@php
    $hasChildren = ! $isChild && $category->children->isNotEmpty();
    $used = $isChild
        ? $category->tickets_count > 0
        : ($category->tickets_count + $category->children->sum('tickets_count')) > 0;
    $followsParentOff = $isChild && ! $parentActive;
@endphp

<div @class([
    'grid gap-3 px-4 py-3.5 md:grid-cols-[minmax(0,1fr)_9rem_7rem_9rem_auto] md:items-center md:gap-4 md:px-5',
    'bg-sunken/40' => $isChild,
    'opacity-70' => ! $category->is_active || $followsParentOff,
])>
    {{-- Kategori --}}
    <div class="min-w-0">
        <div @class(['flex flex-wrap items-center gap-2', 'pl-5 md:pl-7' => $isChild])>
            @if ($isChild)
                <span class="text-line-strong" aria-hidden="true">└</span>
                <span class="sr-only">Jenis Masalah:</span>
            @endif
            @if ($category->is_standard)
                <x-ui.badge tone="primary">Standar</x-ui.badge>
            @else
                <x-ui.badge tone="info">Tambahan</x-ui.badge>
            @endif
            <span @class(['font-bold text-ink' => ! $isChild, 'font-semibold text-ink' => $isChild])>{{ $category->name }}</span>
            @if (! $isChild && $category->isSensitive())
                <x-ui.badge tone="danger">Sensitif</x-ui.badge>
            @endif
        </div>
        @if ($category->description)
            <p @class(['mt-1 text-sm text-muted', 'pl-5 md:pl-7' => $isChild])>{{ $category->description }}</p>
        @endif
    </div>

    {{-- Audiens: hanya di kelompok (anak mewarisi) --}}
    <div class="text-sm">
        <span class="text-xs font-bold text-muted uppercase md:hidden">Audiens: </span>
        @if ($isChild)
            <span class="text-muted">ikut kelompok</span>
        @else
            <span class="inline-flex flex-wrap gap-1">
                @forelse ($category->audienceTypes() as $type)
                    <x-ui.badge>{{ $type->label() }}</x-ui.badge>
                @empty
                    <span class="text-ditolak-fg">belum diatur</span>
                @endforelse
            </span>
        @endif
    </div>

    {{-- SLA --}}
    <div class="text-sm">
        <span class="text-xs font-bold text-muted uppercase md:hidden">SLA: </span>
        @if ($hasChildren)
            <span class="text-muted" title="Nilai awal untuk Jenis Masalah baru">default {{ $category->sla_hours }} jam</span>
        @else
            <span class="font-mono font-semibold">{{ $category->sla_hours }} jam</span>
        @endif
    </div>

    {{-- Status --}}
    <div class="text-sm">
        @if (! $category->is_active)
            <x-ui.badge>Nonaktif</x-ui.badge>
        @elseif ($followsParentOff)
            <x-ui.badge>Nonaktif (ikut kelompok)</x-ui.badge>
        @else
            <x-ui.badge tone="success">Aktif</x-ui.badge>
        @endif
        @if ($used)
            <p class="mt-1 text-xs text-muted">Sudah dipakai tiket</p>
        @endif
    </div>

    {{-- Aksi --}}
    <div class="flex flex-wrap items-center gap-2 md:justify-end">
        <a href="{{ route('admin.services.edit', $category) }}"
           class="inline-flex min-h-10 items-center gap-1.5 rounded-control border border-line bg-surface px-3 text-sm font-bold text-ink shadow-sm hover:bg-sunken">
            <x-icon name="pencil" class="size-4" /> Ubah<span class="sr-only"> {{ $category->name }}</span>
        </a>

        @if ($category->is_active)
            <x-ui.confirm-form :action="route('admin.services.status', $category)" method="PATCH" compact
                               trigger-variant="secondary" trigger-label="Nonaktifkan"
                               title="Nonaktifkan {{ $category->name }}?" confirm-label="Ya, nonaktifkan">
                <x-slot:fields><input type="hidden" name="is_active" value="0"></x-slot:fields>
                Kategori ini tidak akan muncul lagi di form tiket pelapor.
                @if ($hasChildren)
                    Semua Jenis Masalah di bawahnya ({{ $category->children->count() }}) ikut tersembunyi.
                @endif
                Tiket yang sudah ada tidak terpengaruh, dan Anda bisa mengaktifkannya kembali kapan saja.
            </x-ui.confirm-form>
        @else
            <form method="POST" action="{{ route('admin.services.status', $category) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="is_active" value="1">
                <button type="submit" class="inline-flex min-h-10 items-center gap-1.5 rounded-control bg-primary-soft px-3 font-heading text-sm font-extrabold text-primary hover:brightness-95">
                    Aktifkan
                </button>
            </form>
        @endif

        @if (! $category->is_standard)
            @if ($used)
                <span class="inline-flex min-h-10 cursor-not-allowed items-center gap-1.5 rounded-control px-3 text-sm font-bold text-muted"
                      title="Sudah dipakai tiket: nonaktifkan saja">
                    <x-icon name="trash" class="size-4" /> Tidak bisa dihapus
                </span>
            @else
                <x-ui.confirm-form :action="route('admin.services.destroy', $category)" method="DELETE" compact icon="trash"
                                   trigger-label="Hapus" title="Hapus {{ $category->name }}?" confirm-label="Ya, hapus permanen">
                    Kategori tambahan ini belum pernah dipakai tiket dan akan dihapus permanen.
                    @if ($hasChildren)
                        {{ $category->children->count() }} Jenis Masalah di bawahnya ikut terhapus.
                    @endif
                    Pemetaan spesialisasi staff ke kategori ini juga dihapus.
                </x-ui.confirm-form>
            @endif
        @endif

        @if (! $isChild && $canHaveChildren)
            <a href="{{ route('admin.services.create', ['parent_id' => $category->id]) }}"
               class="inline-flex min-h-10 items-center gap-1.5 rounded-control px-3 text-sm font-bold text-primary hover:bg-primary-soft">
                <x-icon name="plus" class="size-4" /> Jenis Masalah
            </a>
        @endif
    </div>
</div>
