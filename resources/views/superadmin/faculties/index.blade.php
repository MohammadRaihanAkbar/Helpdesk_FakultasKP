{{--
    Master Fakultas (/super-admin/faculties) — US-17, F-SPA-01, PRD 7.4.
    Tabel: kode & nama, status, statistik ringkas (Admin, Staff, Total Tiket, Kelompok tanpa Staff), aksi (ubah, aktif/nonaktif).
--}}
<x-layouts.app title="Fakultas">
    <x-page-header title="Fakultas" description="Data master fakultas UIN SSC. Fakultas nonaktif tidak muncul di form buat tiket.">
        <x-slot:action>
            <x-ui.button :href="route('superadmin.faculties.create')" icon="plus">Tambah Fakultas</x-ui.button>
        </x-slot:action>
    </x-page-header>

    <x-ui.card class="p-0! md:p-0!">
        @if ($faculties->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="landmark" title="Belum ada fakultas">
                    Tambahkan fakultas pertama. Katalog Kategori Layanan Standar akan tersalin otomatis.
                </x-ui.empty-state>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[52rem] text-left text-sm">
                    <thead class="border-b border-line bg-sunken/60 text-xs font-extrabold tracking-wide text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-5 py-3">Fakultas</th>
                            <th scope="col" class="px-3 py-3">Status</th>
                            <th scope="col" class="px-3 py-3 text-center">Admin</th>
                            <th scope="col" class="px-3 py-3 text-center">Staff</th>
                            <th scope="col" class="px-3 py-3 text-center">Total Tiket</th>
                            <th scope="col" class="px-3 py-3 text-center">Kelompok tanpa Staff</th>
                            <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($faculties as $faculty)
                            <tr @class(['align-top', 'opacity-70' => ! $faculty->is_active])>
                                <td class="px-5 py-3.5">
                                    <p class="font-bold text-ink"><span class="font-mono">{{ $faculty->code }}</span> — {{ $faculty->name }}</p>
                                    <p class="text-xs text-muted">slug: {{ $faculty->slug }}@if ($faculty->description) · {{ \Illuminate\Support\Str::limit($faculty->description, 80) }}@endif</p>
                                </td>
                                <td class="px-3 py-3.5">
                                    @if ($faculty->is_active)
                                        <x-ui.badge tone="success">Aktif</x-ui.badge>
                                    @else
                                        <x-ui.badge>Nonaktif</x-ui.badge>
                                    @endif
                                </td>
                                <td @class(['px-3 py-3.5 text-center font-mono font-semibold', 'text-ditolak-fg' => $faculty->admins_count === 0])>
                                    {{ $faculty->admins_count }}
                                </td>
                                <td class="px-3 py-3.5 text-center font-mono font-semibold">
                                    {{ $faculty->active_staff_count }}
                                    @if ($faculty->staff_count > $faculty->active_staff_count)
                                        <span class="block text-xs font-normal text-muted">+{{ $faculty->staff_count - $faculty->active_staff_count }} nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5 text-center font-mono font-semibold">{{ $faculty->tickets_count }}</td>
                                <td class="px-3 py-3.5 text-center">
                                    @if ($faculty->uncovered_count > 0)
                                        <x-ui.badge tone="danger">{{ $faculty->uncovered_count }} kelompok</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="success">Lengkap</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('superadmin.faculties.edit', $faculty) }}"
                                           class="inline-flex min-h-10 items-center gap-1.5 rounded-control border border-line bg-surface px-3 text-sm font-bold text-ink shadow-sm hover:bg-sunken">
                                            <x-icon name="pencil" class="size-4" /> Ubah<span class="sr-only"> {{ $faculty->code }}</span>
                                        </a>
                                        @if ($faculty->is_active)
                                            <x-ui.confirm-form :action="route('superadmin.faculties.status', $faculty)" method="PATCH" compact
                                                               trigger-variant="secondary" trigger-label="Nonaktifkan"
                                                               title="Nonaktifkan {{ $faculty->code }}?" confirm-label="Ya, nonaktifkan">
                                                <x-slot:fields><input type="hidden" name="is_active" value="0"></x-slot:fields>
                                                {{ $faculty->name }} tidak akan muncul lagi di form buat tiket.
                                                Tiket yang sudah ada, akun Admin, dan akun Staff-nya tetap ada.
                                            </x-ui.confirm-form>
                                        @else
                                            <form method="POST" action="{{ route('superadmin.faculties.status', $faculty) }}">
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

    <p class="mt-3 flex items-start gap-1.5 text-xs text-muted">
        <x-icon name="info" class="mt-0.5 size-4 shrink-0" />
        Staff = akun Staff aktif. "Kelompok tanpa Staff" = Kelompok Layanan aktif yang belum dipegang Staff aktif; penugasannya wewenang Admin Fakultas.
    </p>
</x-layouts.app>
