{{--
    Kelola Staff Fakultas (/admin/staff) — PRD 10.6, US-12.
    Tabel: nama, email, spesialisasi, status, jumlah tiket aktif, aksi (spesialisasi, aktif/nonaktif).
--}}
<x-layouts.app title="Kelola Staff">
    <x-page-header title="Kelola Staff: {{ $faculty->code }}" description="Akun staff {{ $faculty->name }} dan spesialisasi layanannya.">
        <x-slot:action>
            <x-ui.button :href="route('admin.staff.create')" icon="plus">Tambah Staff Baru</x-ui.button>
        </x-slot:action>
    </x-page-header>

    <div class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_22rem]">
        <x-ui.card class="p-0! md:p-0!">
            @if ($staff->isEmpty())
                <div class="p-5">
                    <x-ui.empty-state icon="users" title="Belum ada staff">
                        Tambahkan akun staff pertama, lalu tetapkan spesialisasinya supaya tiket masuk ke antreannya.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[44rem] text-left text-sm">
                        <thead class="border-b border-line bg-sunken/60 text-xs font-extrabold tracking-wide text-muted uppercase">
                            <tr>
                                <th scope="col" class="px-5 py-3">Staff</th>
                                <th scope="col" class="px-3 py-3">Spesialisasi</th>
                                <th scope="col" class="px-3 py-3">Status</th>
                                <th scope="col" class="px-3 py-3 text-center">Tiket aktif</th>
                                <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($staff as $member)
                                <tr @class(['align-top', 'opacity-70' => ! $member->is_active])>
                                    <td class="px-5 py-3.5">
                                        <p class="font-bold text-ink">{{ $member->name }}</p>
                                        <p class="text-muted">{{ $member->email }}</p>
                                    </td>
                                    <td class="px-3 py-3.5">
                                        @forelse ($member->specialties as $group)
                                            <span class="mr-1 mb-1 inline-flex"><x-ui.badge :tone="$group->is_active ? 'neutral' : 'warning'">{{ $group->name }}{{ $group->is_active ? '' : ' (nonaktif)' }}</x-ui.badge></span>
                                        @empty
                                            <span class="text-ditolak-fg">(belum ada)</span>
                                        @endforelse
                                    </td>
                                    <td class="px-3 py-3.5">
                                        @if ($member->is_active)
                                            <x-ui.badge tone="success">Aktif</x-ui.badge>
                                        @else
                                            <x-ui.badge>Nonaktif</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3.5 text-center font-mono font-semibold">{{ $member->active_tickets_count }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <a href="{{ route('admin.staff.specialties.edit', $member) }}"
                                               class="inline-flex min-h-10 items-center gap-1.5 rounded-control border border-line bg-surface px-3 text-sm font-bold text-ink shadow-sm hover:bg-sunken">
                                                <x-icon name="sliders" class="size-4" /> Spesialisasi<span class="sr-only"> {{ $member->name }}</span>
                                            </a>
                                            @if ($member->is_active)
                                                <x-ui.confirm-form :action="route('admin.staff.status', $member)" method="PATCH" compact
                                                                   trigger-variant="secondary" trigger-label="Nonaktifkan"
                                                                   title="Nonaktifkan {{ $member->name }}?" confirm-label="Ya, nonaktifkan">
                                                    <x-slot:fields><input type="hidden" name="is_active" value="0"></x-slot:fields>
                                                    Staff ini tidak bisa login lagi sampai diaktifkan kembali.
                                                    @if ($member->active_tickets_count > 0)
                                                        Ia masih memegang {{ $member->active_tickets_count }} tiket berstatus Diproses; tiket itu tetap ada.
                                                    @endif
                                                </x-ui.confirm-form>
                                            @else
                                                <form method="POST" action="{{ route('admin.staff.status', $member) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="is_active" value="1">
                                                    <button type="submit" class="inline-flex min-h-10 items-center rounded-control bg-primary-soft px-3 font-heading text-sm font-extrabold text-primary hover:brightness-95">Aktifkan</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>

        <x-coverage-summary :coverage="$coverage" />
    </div>
</x-layouts.app>
