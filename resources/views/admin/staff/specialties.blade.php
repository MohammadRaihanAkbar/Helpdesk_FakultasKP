{{--
    Spesialisasi Staff (/admin/staff/{id}/specialties) — PRD 10.6, US-13.
    Checkbox Kelompok Layanan AKTIF, dikelompokkan menurut audiens. Jenis Masalah tampil sebagai keterangan.
    "Integritas Dosen" diberi label Sensitif; saat dicentang muncul peringatan (Alpine).
--}}
<x-layouts.app title="Spesialisasi Staff">
    <x-page-header title="Spesialisasi: {{ $staff->name }}" :back="route('admin.staff.index')"
                   description="Staff hanya melihat tiket dari Kelompok Layanan yang dicentang, termasuk semua Jenis Masalah di bawahnya." />

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <x-ui.card>
            <form method="POST" action="{{ route('admin.staff.specialties.update', $staff) }}" class="space-y-6">
                @csrf
                @method('PUT')

                @error('category_ids')
                    <x-ui.alert type="error">{{ $message }}</x-ui.alert>
                @enderror

                @if (! $staff->is_active)
                    <x-ui.alert type="warning">Akun ini sedang nonaktif, jadi tidak dihitung dalam cakupan sampai diaktifkan kembali.</x-ui.alert>
                @endif

                @foreach ($options as $bucket => $groups)
                    <fieldset>
                        <legend class="mb-2 text-xs font-extrabold tracking-[.12em] text-muted uppercase">{{ $bucket }}</legend>
                        <div class="space-y-2">
                            @foreach ($groups as $group)
                                @php($sensitive = $group->isSensitive())
                                <div x-data="{ checked: @js(in_array($group->id, array_map('intval', $selected), true)) }">
                                    <label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-control border border-line bg-surface px-4 py-3 has-checked:border-primary has-checked:bg-primary-soft">
                                        <input type="checkbox" name="category_ids[]" value="{{ $group->id }}" x-model="checked"
                                               class="mt-0.5 size-5 shrink-0 accent-primary" @checked(in_array($group->id, array_map('intval', $selected), true))
                                               @if ($sensitive) aria-describedby="sensitif-{{ $group->id }}" @endif>
                                        <span class="min-w-0">
                                            <span class="font-semibold text-ink">{{ $group->name }}</span>
                                            @if ($sensitive)
                                                <x-ui.badge tone="danger" class="ml-1">⚠ Sensitif</x-ui.badge>
                                            @endif
                                            @if ($group->children->isNotEmpty())
                                                <span class="block text-sm text-muted">({{ $group->children->pluck('name')->implode(', ') }})</span>
                                            @endif
                                        </span>
                                    </label>
                                    @if ($sensitive)
                                        <p id="sensitif-{{ $group->id }}" x-show="checked" x-cloak role="alert"
                                           class="mt-2 flex items-start gap-2 rounded-control bg-ditolak-bg px-4 py-3 text-sm text-ditolak-fg">
                                            <x-icon name="triangle-alert" class="mt-0.5 size-4" />
                                            <span>Kategori sensitif — petakan hanya ke staf yang ditunjuk (mis. Dekanat/Penjaminan Mutu). Identitas pelapor hanya terlihat oleh staff penangan dan Admin Fakultas.</span>
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <div class="flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end">
                    <x-ui.button variant="secondary" :href="route('admin.staff.index')">Batal</x-ui.button>
                    <x-ui.button type="submit" icon="circle-check">Simpan Spesialisasi</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-coverage-summary :coverage="$coverage" />
    </div>
</x-layouts.app>
