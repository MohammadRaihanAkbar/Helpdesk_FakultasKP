{{--
    Pengaturan Layanan (/admin/services) — PRD 10.8, US-11.
    Pohon 2 tingkat: Kelompok Layanan -> Jenis Masalah, label Standar/Tambahan, audiens, SLA, status, aksi.
--}}
<x-layouts.app title="Pengaturan Layanan">
    <x-page-header title="Pengaturan Layanan — {{ $faculty->code }}"
                   description="Katalog kategori layanan {{ $faculty->name }} beserta target SLA (jam).">
        <x-slot:action>
            <x-ui.button :href="route('admin.services.create')" icon="plus">Kategori Tambahan</x-ui.button>
        </x-slot:action>
    </x-page-header>

    <x-ui.card class="p-0! md:p-0!">
        <div class="hidden grid-cols-[minmax(0,1fr)_9rem_7rem_9rem_auto] gap-4 border-b border-line bg-sunken/60 px-5 py-3 text-xs font-extrabold tracking-wide text-muted uppercase md:grid">
            <span>Kategori</span><span>Audiens</span><span>SLA</span><span>Status</span><span class="text-right">Aksi</span>
        </div>

        <ul class="divide-y divide-line">
            @foreach ($groups as $group)
                <li>
                    @include('admin.services._row', [
                        'category' => $group,
                        'isChild' => false,
                        'canHaveChildren' => ! $group->is_standard || $group->children->isNotEmpty(),
                    ])
                    @if ($group->children->isNotEmpty())
                        <ul class="divide-y divide-line border-t border-line">
                            @foreach ($group->children as $child)
                                <li>
                                    @include('admin.services._row', [
                                        'category' => $child->setRelation('parent', $group),
                                        'isChild' => true,
                                        'parentActive' => $group->is_active,
                                        'canHaveChildren' => false,
                                    ])
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-ui.card>

    <div class="mt-4 grid gap-2 text-sm text-muted md:grid-cols-2">
        <p class="flex items-start gap-2"><x-icon name="pencil" class="mt-0.5 size-4" /> <span><b class="text-ink">Standar:</b> hanya Target SLA, deskripsi, dan status Aktif yang bisa diubah. Tidak bisa dihapus.</span></p>
        <p class="flex items-start gap-2"><x-icon name="trash" class="mt-0.5 size-4" /> <span><b class="text-ink">Tambahan:</b> bisa diubah penuh; hapus hanya jika belum pernah dipakai tiket.</span></p>
        <p class="flex items-start gap-2"><x-icon name="info" class="mt-0.5 size-4" /> <span>Kelompok yang dinonaktifkan ikut menyembunyikan semua Jenis Masalah di bawahnya dari form tiket.</span></p>
        <p class="flex items-start gap-2"><x-icon name="clock" class="mt-0.5 size-4" /> <span>Perubahan SLA hanya berlaku untuk tiket <b class="text-ink">baru</b>; tiket lama tetap memakai SLA saat dibuat.</span></p>
    </div>
</x-layouts.app>
