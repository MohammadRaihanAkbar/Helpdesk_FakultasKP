{{--
    Form kategori (tambah kategori tambahan / ubah kategori). PRD US-11.

    - Kategori STANDAR: nama, induk, audiens ditampilkan terkunci; yang bisa diubah hanya SLA, deskripsi, Aktif.
    - Kategori TAMBAHAN: semua field. Alpine:
        * pilih induk  -> blok audiens disembunyikan (Jenis Masalah mewarisi audiens induk)
        * pilih induk  -> Target SLA terisi otomatis dari SLA induk (masih bisa diubah)
--}}
@php
    $isEdit = $category->exists;
    $locked = $isEdit && $category->is_standard;
    $title = $isEdit ? 'Ubah Kategori' : 'Tambah Kategori Tambahan';
    $currentAudiences = old('audiences', $category->audiences?->pluck('user_type')->map->value->all() ?? []);
    $parentSla = $parents->mapWithKeys(fn ($p) => [$p->id => $p->sla_hours]);
@endphp

<x-layouts.app :title="$title">
    <x-page-header :title="$title" :back="route('admin.services.index')"
                   :description="$locked ? 'Kategori standar: hanya target batas waktu, deskripsi, dan status Aktif yang bisa diubah.' : 'Kategori tambahan khas fakultas Anda.'" />

    <x-ui.card class="max-w-3xl">
        <form method="POST" action="{{ $isEdit ? route('admin.services.update', $category) : route('admin.services.store') }}"
              class="space-y-5"
              x-data="{
                  parent: @js((string) old('parent_id', $selectedParent?->id ?? '')),
                  sla: @js((string) old('sla_hours', $isEdit ? $category->sla_hours : ($selectedParent?->sla_hours ?? ''))),
                  parentSla: @js($parentSla),
                  pickParent() { if (this.parent && this.parentSla[this.parent]) this.sla = String(this.parentSla[this.parent]); }
              }">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            @if ($locked)
                <x-ui.alert type="info" title="Kategori standar terkunci">
                    Nama, kelompok induk, dan audiens kategori standar sama untuk semua fakultas agar laporan dan perbandingan waktu penyelesaian tetap sebanding.
                </x-ui.alert>

                <dl class="grid gap-4 rounded-control border border-line bg-sunken/50 p-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-muted">Nama</dt>
                        <dd class="mt-0.5 flex items-center gap-1.5 font-bold"><x-icon name="lock" class="size-4 text-muted" /> {{ $category->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Kelompok induk</dt>
                        <dd class="mt-0.5 font-semibold">{{ $category->parent?->name ?? '(Kelompok Layanan)' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Audiens</dt>
                        <dd class="mt-0.5 font-semibold">{{ collect($category->audienceTypes())->map->label()->implode(', ') }}</dd>
                    </div>
                </dl>
            @else
                <x-ui.input name="name" label="Nama kategori *" :value="$category->name" maxlength="100" required
                            hint="Contoh: Laboratorium Microteaching. Nama harus unik di fakultas Anda." />

                <div>
                    <label for="parent_id" class="mb-1.5 block text-sm font-bold text-ink">Kelompok induk (opsional)</label>
                    <select id="parent_id" name="parent_id" x-model="parent" @change="pickParent()"
                            @class(['block min-h-13 w-full rounded-control border bg-surface px-3 text-base shadow-sm focus:border-accent focus:ring-4 focus:ring-accent/30 focus:outline-none',
                                    'border-line' => ! $errors->has('parent_id'), 'border-ditolak-fg' => $errors->has('parent_id')])
                            aria-describedby="parent_id-hint" @if ($errors->has('parent_id')) aria-invalid="true" @endif
                            @disabled($isEdit && $category->children->isNotEmpty())>
                        <option value="">(Tidak ada: jadikan Kelompok Layanan baru)</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}">{{ $parent->name }} ({{ $parent->is_standard ? 'Standar' : 'Tambahan' }})</option>
                        @endforeach
                    </select>
                    <p id="parent_id-hint" class="mt-1.5 text-sm text-muted">
                        @if ($isEdit && $category->children->isNotEmpty())
                            Kelompok ini punya Jenis Masalah, jadi tidak bisa dipindah ke bawah kelompok lain.
                        @else
                            Pilih induk untuk membuat <b>Jenis Masalah</b>. Hanya kelompok tambahan dan kelompok standar yang sudah punya Jenis Masalah yang bisa dipilih.
                        @endif
                    </p>
                    @error('parent_id')
                        <p class="mt-1.5 flex items-start gap-1.5 text-sm text-ditolak-fg"><x-icon name="circle-alert" class="mt-0.5 size-4" /><span>{{ $message }}</span></p>
                    @enderror
                    @if ($isEdit && $category->children->isNotEmpty())
                        <input type="hidden" name="parent_id" value="">
                    @endif
                </div>

                <fieldset x-show="!parent">
                    <legend class="mb-1.5 text-sm font-bold text-ink">Audiens * <span class="font-normal text-muted">(jenis pelapor yang bisa memilih kelompok ini)</span></legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($userTypes as $type)
                            <label class="inline-flex min-h-12 cursor-pointer items-center gap-2.5 rounded-control border border-line bg-surface px-4 shadow-sm has-checked:border-primary has-checked:bg-primary-soft">
                                <input type="checkbox" name="audiences[]" value="{{ $type->value }}" class="size-5 accent-primary"
                                       @checked(in_array($type->value, $currentAudiences, true))>
                                <span class="font-semibold">{{ $type->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('audiences')
                        <p class="mt-1.5 flex items-start gap-1.5 text-sm text-ditolak-fg"><x-icon name="circle-alert" class="mt-0.5 size-4" /><span>{{ $message }}</span></p>
                    @enderror
                </fieldset>
                <p x-show="parent" x-cloak class="rounded-control bg-sunken px-4 py-3 text-sm text-muted">
                    Jenis Masalah otomatis memakai audiens kelompok induknya.
                </p>
            @endif

            <div class="max-w-xs">
                <label for="sla_hours" class="mb-1.5 block text-sm font-bold text-ink">Target Batas Waktu (jam) *</label>
                <div class="relative">
                    <input id="sla_hours" name="sla_hours" type="number" min="1" max="{{ \App\Http\Requests\Admin\CategoryRequest::SLA_MAX }}" inputmode="numeric"
                           x-model="sla" aria-describedby="sla_hours-hint" @if ($errors->has('sla_hours')) aria-invalid="true" @endif
                           @class(['block min-h-13 w-full rounded-control border bg-surface pr-14 pl-3.5 font-mono text-base shadow-sm focus:border-accent focus:ring-4 focus:ring-accent/30 focus:outline-none',
                                   'border-line' => ! $errors->has('sla_hours'), 'border-ditolak-fg' => $errors->has('sla_hours')])>
                    <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-sm text-muted">jam</span>
                </div>
                <p id="sla_hours-hint" class="mt-1.5 text-sm text-muted">
                    Contoh 24, 48, 72. Berlaku untuk tiket baru.
                    <span x-show="parent" x-cloak>Default diambil dari target waktu kelompok induk.</span>
                    @if ($isEdit && $category->isGroup() && $category->children->isNotEmpty())
                        Pada kelompok yang punya Jenis Masalah, nilai ini menjadi default untuk Jenis Masalah baru.
                    @endif
                </p>
                @error('sla_hours')
                    <p class="mt-1.5 flex items-start gap-1.5 text-sm text-ditolak-fg"><x-icon name="circle-alert" class="mt-0.5 size-4" /><span>{{ $message }}</span></p>
                @enderror
            </div>

            <x-ui.textarea name="description" label="Deskripsi" :value="$category->description" :maxlength="1000" rows="3"
                           hint="Contoh aduan, supaya pelapor tidak salah pilih kategori." />

            <div>
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex min-h-12 cursor-pointer items-center gap-3">
                    <input type="checkbox" name="is_active" value="1" class="size-5 accent-primary" @checked(old('is_active', $category->is_active ?? true))>
                    <span><span class="font-bold">Aktif</span> <span class="text-sm text-muted">(tampil di form buat tiket)</span></span>
                </label>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="secondary" :href="route('admin.services.index')">Batal</x-ui.button>
                <x-ui.button type="submit" icon="circle-check">{{ $isEdit ? 'Simpan Perubahan' : 'Tambah Kategori' }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
